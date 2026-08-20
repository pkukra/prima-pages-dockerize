import React, { useEffect, useMemo, useRef, useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import {
    Alert,
    Button,
    Card,
    Col,
    Divider,
    Popconfirm,
    Row,
    Select,
    Space,
    Spin,
    Tag,
    Typography,
    message,
} from "antd";
import {
    DeleteOutlined,
    EditOutlined,
    FilePdfOutlined,
    PlusOutlined,
} from "@ant-design/icons";
import { Document as PdfDocument, Page, pdfjs } from "react-pdf";
import { Rnd } from "react-rnd";
import axios from "axios";
import { data } from "autoprefixer";

pdfjs.GlobalWorkerOptions.workerSrc =
    `//unpkg.com/pdfjs-dist@${pdfjs.version}/build/pdf.worker.min.mjs`;

const { Title, Paragraph, Text } = Typography;

const clamp = (value, min = 0, max = 1) =>
    Math.max(min, Math.min(max, value));
const sizeChanged = (prevSize, nextSize) => {
    if (!prevSize) return true;
    const widthDiff = Math.abs((prevSize.width || 0) - (nextSize.width || 0));
    const heightDiff = Math.abs(
        (prevSize.height || 0) - (nextSize.height || 0),
    );
    return widthDiff > 0.5 || heightDiff > 0.5;
};

export default function View({ auth, docId }) {
    const currentUserId = Number(auth?.user?.id || 0);
    const currentUserEmail = (auth?.user?.email || "").toLowerCase();
    const currentUserRole = (auth?.user?.role?.name || "").toLowerCase();
    const canManageSigners =
        currentUserRole === "admin" || currentUserRole === "superadmin";
    const [loadingDocument, setLoadingDocument] = useState(true);
    const [loadingSignature, setLoadingSignature] = useState(true);
    const [documentData, setDocumentData] = useState(null);
    const [signing, setSigning] = useState(false);
    const [addingSigners, setAddingSigners] = useState(false);
    const [removingSignerId, setRemovingSignerId] = useState(null);
    const [signerCandidates, setSignerCandidates] = useState([]);
    const [selectedSignerIds, setSelectedSignerIds] = useState([]);

    const [numPages, setNumPages] = useState(0);
    const [pdfError, setPdfError] = useState("");
    const [renderWidth, setRenderWidth] = useState(900);
    const [pageSizes, setPageSizes] = useState({});

    const [signaturePath, setSignaturePath] = useState("");
    const [signatureImageUrl, setSignatureImageUrl] = useState("");

    const [placements, setPlacements] = useState([]);
    const [addPage, setAddPage] = useState(1);

    const previewContainerRef = useRef(null);
    const pageResizeObserverRef = useRef(null);
    const pageRefs = useRef({});

    const pageOptions = useMemo(
        () =>
            Array.from({ length: numPages }, (_, index) => ({
                label: `Halaman ${index + 1}`,
                value: index + 1,
            })),
        [numPages],
    );

    const isPdfDocument = useMemo(() => {
        const path = (documentData?.file_path || "").toLowerCase();
        return path.endsWith(".pdf");
    }, [documentData]);

    const ownPlacements = useMemo(
        () => placements.filter((item) => item.editable),
        [placements],
    );

    const existingSignerIds = useMemo(() => {
        return new Set(
            (documentData?.signers || [])
                .map((item) => Number(item.user_id || 0))
                .filter((id) => id > 0),
        );
    }, [documentData?.signers]);

    const signerOptions = useMemo(() => {
        return signerCandidates
            .filter((item) => !existingSignerIds.has(Number(item.id || 0)))
            .map((item) => ({
                value: item.id,
                label: `${item.name} (${item.email})`,
            }));
    }, [signerCandidates, existingSignerIds]);

    const fetchDocument = async () => {
        setLoadingDocument(true);
        try {
            const response = await axios.get(route("docu.show", { id: docId }));
            const doc = response?.data?.data || null;
            setDocumentData(doc);

            const existingPlacements = (doc?.signatures || []).map(
                (item, index) => ({
                    id: `existing-${item.id}-${index}`,
                    page: Number(item.page) || 1,
                    x: Number(item.x) || 0,
                    y: Number(item.y) || 0,
                    width: Number(item.width) || 0.2,
                    height: Number(item.height) || 0.08,
                    sort_order: Number(item.sort_order) || index + 1,
                    user_id: Number(item.user_id) || null,
                    created_by: item.created_by || "",
                    editable:
                        (Number(item.user_id) || 0) === currentUserId ||
                        (!item.user_id &&
                            item.created_by &&
                            item.created_by.toLowerCase() === currentUserEmail),
                }),
            );

            setPlacements(existingPlacements);
        } catch (error) {
            console.error(error);
            const msg =
                error?.response?.data?.message ||
                "Gagal memuat detail dokumen";
            message.error(msg);
            setDocumentData(null);
        } finally {
            setLoadingDocument(false);
        }
    };

    const fetchSignatureProfile = async () => {
        setLoadingSignature(true);
        try {
            const response = await axios.get(route("tilaka.profile.show"));
            const profile = response?.data?.data || null;

            if (profile?.signature_path) {
                setSignaturePath(profile.signature_path);
                setSignatureImageUrl(
                    route("tilaka.profile.preview", {
                        documentType: "signature",
                    }),
                );
            } else {
                setSignaturePath("");
                setSignatureImageUrl("");
            }
        } catch (error) {
            console.error(error);
            setSignaturePath("");
            setSignatureImageUrl("");
        } finally {
            setLoadingSignature(false);
        }
    };

    const fetchSignerCandidates = async () => {
        if (!canManageSigners) {
            return;
        }

        try {
            const response = await axios.get(route("docu.list_signers"));
            setSignerCandidates(response?.data?.data || []);
        } catch (error) {
            console.error(error);
            message.error("Gagal memuat data kandidat signer");
        }
    };

    useEffect(() => {
        fetchDocument();
        fetchSignatureProfile();
        fetchSignerCandidates();
    }, []);

    useEffect(() => {
        if (!documentData?.signers?.length) {
            return;
        }

        setSelectedSignerIds((prev) =>
            prev.filter((id) => !existingSignerIds.has(Number(id || 0))),
        );
    }, [documentData?.signers, existingSignerIds]);

    useEffect(() => {
        if (!previewContainerRef.current || typeof ResizeObserver === "undefined") {
            return undefined;
        }

        const observer = new ResizeObserver((entries) => {
            const entry = entries[0];
            if (!entry) return;

            const width = Math.floor(entry.contentRect.width);
            if (width > 0) {
                setRenderWidth(Math.max(320, width - 24));
            }
        });

        observer.observe(previewContainerRef.current);

        return () => {
            observer.disconnect();
        };
    }, []);

    useEffect(() => {
        if (typeof ResizeObserver === "undefined") {
            return undefined;
        }

        const observer = new ResizeObserver((entries) => {
            setPageSizes((prev) => {
                const next = { ...prev };
                let changed = false;
                entries.forEach((entry) => {
                    const pageNumber = Number(
                        entry.target.getAttribute("data-page-number") || 0,
                    );
                    if (!pageNumber) return;

                    const nextSize = {
                        width: entry.contentRect.width,
                        height: entry.contentRect.height,
                    };
                    if (sizeChanged(prev[pageNumber], nextSize)) {
                        next[pageNumber] = nextSize;
                        changed = true;
                    }
                });
                return changed ? next : prev;
            });
        });

        pageResizeObserverRef.current = observer;
        Object.values(pageRefs.current).forEach((node) => {
            if (node) observer.observe(node);
        });

        return () => {
            observer.disconnect();
            pageResizeObserverRef.current = null;
        };
    }, [numPages]);

    useEffect(() => {
        if (numPages > 0 && addPage > numPages) {
            setAddPage(1);
        }
    }, [numPages, addPage]);

    const setPageNode = (pageNumber, node) => {
        const observer = pageResizeObserverRef.current;
        const previousNode = pageRefs.current[pageNumber];

        if (previousNode && observer) {
            observer.unobserve(previousNode);
        }

        if (!node) {
            delete pageRefs.current[pageNumber];
            return;
        }

        node.setAttribute("data-page-number", String(pageNumber));
        pageRefs.current[pageNumber] = node;

        if (observer) {
            observer.observe(node);
        }
    };

    const addSignaturePlacement = () => {
        if (!documentData?.can_sign) {
            message.error(
                "Anda tidak di-assign sebagai signer untuk dokumen ini.",
            );
            return;
        }

        if (!signatureImageUrl) {
            message.warning("Upload tanda tangan di menu Tilaka terlebih dahulu");
            return;
        }

        if (!numPages) {
            message.warning("PDF belum siap");
            return;
        }

        const page = clamp(addPage, 1, numPages);
        const placement = {
            id: `new-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
            page,
            x: 0.1,
            y: 0.1,
            width: 0.2,
            height: 0.08,
            sort_order: ownPlacements.length + 1,
            user_id: currentUserId,
            created_by: auth?.user?.email || "",
            editable: true,
        };

        setPlacements((prev) => [...prev, placement]);
    };

    const updatePlacement = (placementId, patch) => {
        setPlacements((prev) =>
            prev.map((item) =>
                item.id === placementId && item.editable
                    ? { ...item, ...patch }
                    : item,
            ),
        );
    };

    const removePlacement = (placementId) => {
        setPlacements((prev) =>
            prev.filter((item) => item.id !== placementId || !item.editable),
        );
    };
    
    const sendTemplate = async () => {
        try {
            const response = await axios.post(
                route("docu.add_template", { id: documentData.id }),
                { data: {} },
            );
            console.log(response);
            return;

            const authResponse = response?.data?.data?.auth_response?.[0];
            if (authResponse?.url) {
                window.location.href = authResponse.url;
                return;
            }

            message.success("Dokumen berhasil diproses untuk tanda tangan");
            fetchDocument();
        } catch (error) {
            const msg =
                error?.response?.data?.message ||
                "Gagal melakukan tanda tangan dokumen";
            message.error(msg);
        } finally {
            setSigning(false);
        }
    };

    const signDocument = async () => {
        if (!documentData) return;

        if (!documentData?.can_sign) {
            message.error("Anda tidak di-assign sebagai signer dokumen ini");
            return;
        }

        if (!signaturePath) {
            message.warning(
                "Tanda tangan belum tersedia. Upload di menu Tilaka terlebih dahulu.",
            );
            return;
        }

        if (ownPlacements.length === 0) {
            message.warning("Tambahkan minimal satu placement tanda tangan");
            return;
        }

        setSigning(true);
        try {
            const payloadPlacements = ownPlacements.map((item, index) => ({
                page: Number(item.page),
                x: Number(clamp(item.x, 0, 1).toFixed(8)),
                y: Number(clamp(item.y, 0, 1).toFixed(8)),
                width: Number(clamp(item.width, 0.01, 1).toFixed(8)),
                height: Number(clamp(item.height, 0.01, 1).toFixed(8)),
                sort_order: index + 1,
                signature_path: signaturePath,
            }));

            const response = await axios.post(
                route("docu.sign", { id: documentData.id }),
                { placements: payloadPlacements },
            );

            const authResponse = response?.data?.data?.auth_response?.[0];
            if (authResponse?.url) {
                window.location.href = authResponse.url;
                return;
            }

            message.success("Dokumen berhasil diproses untuk tanda tangan");
            fetchDocument();
        } catch (error) {
            const msg =
                error?.response?.data?.message ||
                "Gagal melakukan tanda tangan dokumen";
            message.error(msg);
        } finally {
            setSigning(false);
        }
    };

    const addSignersToDocument = async () => {
        if (!documentData || !canManageSigners) {
            return;
        }

        if (!selectedSignerIds.length) {
            message.warning("Pilih minimal satu user untuk ditambahkan");
            return;
        }

        setAddingSigners(true);
        try {
            const response = await axios.post(
                route("docu.add_signers", { id: documentData.id }),
                {
                    signer_ids: selectedSignerIds,
                },
            );

            message.success(
                response?.data?.message || "Signer berhasil ditambahkan",
            );
            setSelectedSignerIds([]);
            fetchDocument();
        } catch (error) {
            const msg =
                error?.response?.data?.message ||
                "Gagal menambahkan signer dokumen";
            message.error(msg);
        } finally {
            setAddingSigners(false);
        }
    };

    const removeSignerFromDocument = async (userId) => {
        if (!documentData || !canManageSigners) {
            return;
        }

        setRemovingSignerId(Number(userId));
        try {
            const response = await axios.delete(
                route("docu.remove_signer", {
                    id: documentData.id,
                    userId,
                }),
            );

            message.success(response?.data?.message || "Signer berhasil dihapus");
            fetchDocument();
        } catch (error) {
            const msg =
                error?.response?.data?.message ||
                "Gagal menghapus signer dokumen";
            message.error(msg);
        } finally {
            setRemovingSignerId(null);
        }
    };

    if (loadingDocument || loadingSignature) {
        return (
            <AuthenticatedLayout user={auth.user}>
                <Spin size="large" />
            </AuthenticatedLayout>
        );
    }

    if (!documentData) {
        return (
            <AuthenticatedLayout user={auth.user}>
                <p>Dokumen tidak ditemukan</p>
            </AuthenticatedLayout>
        );
    }

    return (
        <AuthenticatedLayout user={auth.user} header={<p>Detail Dokumen</p>}>
            <Head title={`Dokumen: ${documentData.name}`} />

            <Card style={{ padding: "12px 12px" }}>
                <Title level={4} style={{ marginBottom: 8, wordBreak: "break-word" }}>
                    {documentData.name}
                </Title>
                <Paragraph style={{ marginBottom: 12 }}>
                    {documentData.description || "-"}
                </Paragraph>

                {!signaturePath && (
                    <Alert
                        type="warning"
                        showIcon
                        style={{ marginBottom: 16 }}
                        message="Tanda tangan belum tersedia"
                        description={
                            <span style={{ fontSize: "0.9rem" }}>
                                Upload tanda tangan terlebih dahulu pada menu{" "}
                                <a href={route("tilaka.index")}>Tilaka</a>.
                            </span>
                        }
                    />
                )}

                {!documentData?.can_sign && (
                    <Alert
                        type="info"
                        showIcon
                        style={{ marginBottom: 16 }}
                        message="Anda bukan signer dokumen ini"
                        description={
                            <span style={{ fontSize: "0.9rem" }}>
                                Anda tetap dapat melihat dokumen sesuai hak akses, namun tidak dapat menambahkan atau mengubah placement tanda tangan.
                            </span>
                        }
                    />
                )}

                <Card
                    size="small"
                    title="Progress Signer"
                    style={{ marginBottom: 16 }}
                >
                    <Space direction="vertical" size={8} style={{ width: "100%" }}>
                        {(documentData?.signers || []).map((item) => (
                            <div
                                key={item.id}
                                style={{
                                    display: "flex",
                                    justifyContent: "space-between",
                                    alignItems: "flex-start",
                                    flexWrap: "wrap",
                                    gap: "8px",
                                    padding: "8px 0",
                                }}
                            >
                                <Text
                                    style={{
                                        flex: "1 1 auto",
                                        minWidth: "150px",
                                        wordBreak: "break-word",
                                        fontSize: "0.9rem",
                                    }}
                                >
                                    {item?.user?.name || "-"} ({item?.user?.email || "-"})
                                </Text>
                                <Space size={8} style={{ flexShrink: 0 }}>
                                    <Tag color={item.status_sign === "signed" ? "green" : "orange"}>
                                        {item.status_sign === "signed" ? "SIGNED" : "PENDING"}
                                    </Tag>

                                    {canManageSigners && (
                                        <Popconfirm
                                            title="Hapus signer ini?"
                                            description="Signer akan dihapus dari dokumen dan placement miliknya ikut dihapus."
                                            okText="Ya, Hapus"
                                            cancelText="Batal"
                                            onConfirm={() =>
                                                removeSignerFromDocument(item.user_id)
                                            }
                                            disabled={
                                                (documentData?.signers || []).length <= 1
                                            }
                                        >
                                            <Button
                                                danger
                                                type="text"
                                                icon={<DeleteOutlined />}
                                                loading={
                                                    removingSignerId ===
                                                    Number(item.user_id)
                                                }
                                                disabled={
                                                    (documentData?.signers || []).length <= 1
                                                }
                                                size="small"
                                            />
                                        </Popconfirm>
                                    )}
                                </Space>
                            </div>
                        ))}
                    </Space>

                    {canManageSigners && (
                        <div style={{ marginTop: 12 }}>
                            <Divider style={{ margin: "12px 0" }} />
                            <Space
                                direction="vertical"
                                size={8}
                                style={{ width: "100%" }}
                            >
                                <Text type="secondary" style={{ fontSize: "0.9rem" }}>
                                    Tambah signer ke dokumen
                                </Text>
                                <Select
                                    mode="multiple"
                                    placeholder="Pilih user"
                                    value={selectedSignerIds}
                                    options={signerOptions}
                                    optionFilterProp="label"
                                    onChange={(value) =>
                                        setSelectedSignerIds(value)
                                    }
                                    style={{ width: "100%" }}
                                />
                                <Button
                                    type="primary"
                                    onClick={addSignersToDocument}
                                    loading={addingSigners}
                                    disabled={!signerOptions.length}
                                    block
                                    size="middle"
                                >
                                    Tambahkan Signer
                                </Button>
                            </Space>
                        </div>
                    )}
                </Card>

                <Space
                    wrap
                    style={{
                        width: "100%",
                        justifyContent: "flex-start",
                        gap: "8px",
                    }}
                >
                    <Button
                        type="primary"
                        icon={<FilePdfOutlined />}
                        href={route("docu.download", { id: documentData.id })}
                        target="_blank"
                        size="middle"
                    >
                        Download
                    </Button>

                    {isPdfDocument && (
                        <>
                            <Select
                                style={{ width: "140px", minWidth: "120px" }}
                                value={addPage}
                                options={pageOptions}
                                onChange={setAddPage}
                                disabled={!signaturePath || !numPages}
                                placeholder="Halaman"
                                size="middle"
                            />
                            <Button
                                icon={<PlusOutlined />}
                                onClick={addSignaturePlacement}
                                disabled={
                                    !signaturePath ||
                                    !numPages ||
                                    !documentData?.can_sign
                                }
                                size="middle"
                            >
                                Tambah Tanda Tangan
                            </Button>
                        </>
                    )}

                    <Button
                        type="default"
                        icon={<EditOutlined />}
                        loading={signing}
                        onClick={sendTemplate}
                        disabled={
                            !canManageSigners
                        }
                        size="middle"
                    >
                        Kirim Template
                    </Button>
                    
                    <Button
                        type="default"
                        icon={<EditOutlined />}
                        loading={signing}
                        onClick={signDocument}
                        disabled={
                            !signaturePath ||
                            !isPdfDocument ||
                            !documentData?.can_sign
                        }
                        size="middle"
                    >
                        Konfirmasi & Kirim ke Tilaka
                    </Button>
                </Space>

                <Divider style={{ margin: "12px 0" }} />

                <Row gutter={[12, 16]}>
                    <Col xs={24} md={8} lg={7} style={{ minHeight: "auto" }}>
                        <Card
                            size="small"
                            title={`Placement (${ownPlacements.length}/${placements.length})`}
                            style={{ marginBottom: 16 }}
                        >
                            {placements.length === 0 && (
                                <Text type="secondary" style={{ fontSize: "0.9rem" }}>
                                    Belum ada placement tanda tangan.
                                </Text>
                            )}

                            <Space
                                direction="vertical"
                                size={10}
                                style={{ width: "100%" }}
                            >
                                {placements.map((item, index) => (
                                    <Card
                                        key={item.id}
                                        size="small"
                                        style={{ width: "100%" }}
                                    >
                                        <Space
                                            style={{
                                                width: "100%",
                                                justifyContent: "space-between",
                                                marginBottom: "8px",
                                            }}
                                            align="center"
                                        >
                                            <Tag color="blue">#{index + 1}</Tag>
                                            {item.editable && (
                                                <Button
                                                    danger
                                                    type="text"
                                                    icon={<DeleteOutlined />}
                                                    onClick={() =>
                                                        removePlacement(item.id)
                                                    }
                                                    size="small"
                                                />
                                            )}
                                        </Space>

                                        <div style={{ marginTop: 8 }}>
                                            <Text type="secondary" style={{ fontSize: "0.85rem" }}>
                                                Halaman{" "}
                                                {!item.editable && "(readonly)"}
                                            </Text>
                                            <Select
                                                style={{
                                                    width: "100%",
                                                    marginTop: 6,
                                                }}
                                                value={item.page}
                                                options={pageOptions}
                                                disabled={!item.editable}
                                                onChange={(value) =>
                                                    updatePlacement(item.id, {
                                                        page: value,
                                                    })
                                                }
                                                size="small"
                                            />
                                        </div>
                                    </Card>
                                ))}
                            </Space>
                        </Card>
                    </Col>

                    <Col xs={24} md={16} lg={17}>
                        {!isPdfDocument && (
                            <Alert
                                type="error"
                                showIcon
                                message="Dokumen bukan PDF"
                                description="Fitur placement tanda tangan hanya untuk dokumen PDF."
                            />
                        )}

                        {isPdfDocument && (
                            <div
                                ref={previewContainerRef}
                                style={{
                                    width: "100%",
                                    background: "#f5f5f5",
                                    padding: 12,
                                    borderRadius: 8,
                                    overflowX: "auto",
                                    overflowY: "auto",
                                    maxHeight: "calc(100vh - 400px)",
                                    minHeight: "400px",
                                }}
                            >
                                {pdfError && (
                                    <Alert
                                        type="error"
                                        showIcon
                                        style={{ marginBottom: 12 }}
                                        message="Gagal memuat PDF"
                                        description={pdfError}
                                    />
                                )}

                                <PdfDocument
                                    file={route("docu.preview", {
                                        id: documentData.id,
                                    })}
                                    loading={<Spin />}
                                    onLoadSuccess={({ numPages: pages }) => {
                                        setNumPages(pages);
                                        setPdfError("");
                                    }}
                                    onLoadError={(error) => {
                                        setPdfError(
                                            error?.message || "Gagal memuat PDF",
                                        );
                                    }}
                                >
                                    {Array.from(
                                        { length: numPages },
                                        (_, index) => {
                                            const pageNumber = index + 1;
                                            const size = pageSizes[pageNumber];
                                            const pagePlacements =
                                                placements.filter(
                                                    (item) =>
                                                        Number(item.page) ===
                                                        pageNumber,
                                                );

                                            return (
                                                <div
                                                    key={pageNumber}
                                                    ref={(node) =>
                                                        setPageNode(
                                                            pageNumber,
                                                            node,
                                                        )
                                                    }
                                                    style={{
                                                        position: "relative",
                                                        marginBottom: 16,
                                                        display: "inline-block",
                                                        background: "#fff",
                                                        border: "1px solid #f0f0f0",
                                                    }}
                                                >
                                                    <Tag
                                                        color="geekblue"
                                                        style={{
                                                            position: "absolute",
                                                            top: 8,
                                                            left: 8,
                                                            zIndex: 5,
                                                            fontSize: "0.75rem",
                                                            padding: "4px 8px",
                                                        }}
                                                    >
                                                        Halaman {pageNumber}
                                                    </Tag>

                                                    <Page
                                                        pageNumber={pageNumber}
                                                        width={renderWidth}
                                                        renderAnnotationLayer={
                                                            false
                                                        }
                                                        renderTextLayer={false}
                                                    />

                                                    <div
                                                        style={{
                                                            position: "absolute",
                                                            inset: 0,
                                                            zIndex: 3,
                                                        }}
                                                    >
                                                        {size?.width > 0 &&
                                                            size?.height > 0 &&
                                                            signatureImageUrl &&
                                                            pagePlacements.map(
                                                                (item) => {
                                                                    const widthPx =
                                                                        clamp(
                                                                            item.width,
                                                                            0.01,
                                                                            1,
                                                                        ) *
                                                                        size.width;
                                                                    const heightPx =
                                                                        clamp(
                                                                            item.height,
                                                                            0.01,
                                                                            1,
                                                                        ) *
                                                                        size.height;
                                                                    const xPx =
                                                                        clamp(
                                                                            item.x,
                                                                            0,
                                                                            1,
                                                                        ) *
                                                                        size.width;
                                                                    const yPx =
                                                                        clamp(
                                                                            item.y,
                                                                            0,
                                                                            1,
                                                                        ) *
                                                                        size.height;

                                                                    return (
                                                                        <Rnd
                                                                            key={
                                                                                item.id
                                                                            }
                                                                            bounds="parent"
                                                                            disableDragging={
                                                                                !item.editable
                                                                            }
                                                                            enableResizing={
                                                                                item.editable
                                                                            }
                                                                            size={{
                                                                                width: widthPx,
                                                                                height: heightPx,
                                                                            }}
                                                                            position={{
                                                                                x: xPx,
                                                                                y: yPx,
                                                                            }}
                                                                            onDragStop={(
                                                                                _,
                                                                                data,
                                                                            ) => {
                                                                                if (!item.editable) {
                                                                                    return;
                                                                                }

                                                                                updatePlacement(
                                                                                    item.id,
                                                                                    {
                                                                                        x: clamp(
                                                                                            data.x /
                                                                                                size.width,
                                                                                            0,
                                                                                            1,
                                                                                        ),
                                                                                        y: clamp(
                                                                                            data.y /
                                                                                                size.height,
                                                                                            0,
                                                                                            1,
                                                                                        ),
                                                                                    },
                                                                                );
                                                                            }}
                                                                            onResizeStop={(
                                                                                _,
                                                                                __,
                                                                                ref,
                                                                                ___,
                                                                                position,
                                                                            ) => {
                                                                                if (!item.editable) {
                                                                                    return;
                                                                                }

                                                                                updatePlacement(
                                                                                    item.id,
                                                                                    {
                                                                                        x: clamp(
                                                                                            position.x /
                                                                                                size.width,
                                                                                            0,
                                                                                            1,
                                                                                        ),
                                                                                        y: clamp(
                                                                                            position.y /
                                                                                                size.height,
                                                                                            0,
                                                                                            1,
                                                                                        ),
                                                                                        width: clamp(
                                                                                            ref.offsetWidth /
                                                                                                size.width,
                                                                                            0.01,
                                                                                            1,
                                                                                        ),
                                                                                        height: clamp(
                                                                                            ref.offsetHeight /
                                                                                                size.height,
                                                                                            0.01,
                                                                                            1,
                                                                                        ),
                                                                                    },
                                                                                );
                                                                            }}
                                                                            minWidth={40}
                                                                            minHeight={20}
                                                                            style={{
                                                                                border: item.editable
                                                                                    ? "1px dashed #1677ff"
                                                                                    : "1px dashed #bfbfbf",
                                                                                background:
                                                                                    item.editable
                                                                                        ? "rgba(22, 119, 255, 0.08)"
                                                                                        : "rgba(191, 191, 191, 0.2)",
                                                                                display:
                                                                                    "flex",
                                                                                alignItems:
                                                                                    "center",
                                                                                justifyContent:
                                                                                    "center",
                                                                            }}
                                                                        >
                                                                            <img
                                                                                src={
                                                                                    signatureImageUrl
                                                                                }
                                                                                alt="Signature"
                                                                                style={{
                                                                                    width: "100%",
                                                                                    height: "100%",
                                                                                    objectFit:
                                                                                        "contain",
                                                                                    pointerEvents:
                                                                                        "none",
                                                                                }}
                                                                            />
                                                                        </Rnd>
                                                                    );
                                                                },
                                                            )}
                                                    </div>
                                                </div>
                                            );
                                        },
                                    )}
                                </PdfDocument>
                            </div>
                        )}
                    </Col>
                </Row>
            </Card>
        </AuthenticatedLayout>
    );
}
