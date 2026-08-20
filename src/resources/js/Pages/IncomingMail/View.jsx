import React, { useState, useEffect } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, usePage } from "@inertiajs/react";
import {
    PlusOutlined,
    ShareAltOutlined,
    CopyOutlined,
    DownloadOutlined,
} from "@ant-design/icons";
import {
    Card,
    Row,
    Col,
    Descriptions,
    Button,
    Modal,
    Form,
    Input,
    DatePicker,
    Upload,
    message,
    Select,
    Popconfirm,
    Tag,
    Spin,
} from "antd";
import { UploadOutlined } from "@ant-design/icons";
import axios from "axios";
import dayjs from "dayjs";
import "dayjs/locale/id";
dayjs.locale("id");
import { Table, Timeline } from "antd";

export default function View({ auth }) {
    const { props } = usePage();
    const id = props?.id;

    const [mail, setMail] = useState(props.mail || null);
    const [editVisible, setEditVisible] = useState(false);
    const [docEditVisible, setDocEditVisible] = useState(false);
    const [replaceVisible, setReplaceVisible] = useState(false);
    const [statuses, setStatuses] = useState([]);
    const [mailTypes, setMailTypes] = useState([]);
    const [selectedStatus, setSelectedStatus] = useState(null);
    const [statusChangeLoading, setStatusChangeLoading] = useState(false);
    const [unreadWadir, setUnreadWadir] = useState([]);
    const [loadingWadir, setLoadingWadir] = useState(false);
    const [readWadir, setReadWadir] = useState([]);
    const [loadingReadWadir, setLoadingReadWadir] = useState(false);
    const [dirutRead, setDirutRead] = useState({ read: false });
    const [dispSubmitting, setDispSubmitting] = useState(false);
    const [resolveSubmitting, setResolveSubmitting] = useState(false);

    const [form] = Form.useForm();
    const [docForm] = Form.useForm();
    const canManageIncomingMail = ["superadmin", "admin"].includes(
        auth.user?.role?.name,
    );

    useEffect(() => {
        if (props.mail) setMail(props.mail);
        // Fetch statuses dari API
        axios
            .get(route("incoming.statuses"))
            .then((resp) => setStatuses(resp?.data?.data || []))
            .catch((e) => console.error("Error fetching statuses:", e));

        if (canManageIncomingMail) {
            axios
                .get(route("incoming.types"))
                .then((resp) => setMailTypes(resp?.data?.data || []))
                .catch((e) =>
                    console.error("Error fetching incoming mail types:", e),
                );
        }
    }, [props.mail, canManageIncomingMail]);

    // Auto-mark as read when viewing this mail
    useEffect(() => {
        if (id) {
            axios
                .post(route("incoming.read", { id }))
                .catch((e) => console.error("Error marking mail as read:", e));
        }
    }, [id]);

    // Fetch unread wakil direksi
    useEffect(() => {
        if (id) {
            fetchUnreadWadir();
        }
    }, [id]);

    useEffect(() => {
        if (id) {
            fetchReadTracking();
        }
    }, [id]);

    const fetchUnreadWadir = async () => {
        setLoadingWadir(true);
        try {
            const resp = await axios.get(
                route("incoming.unread_wadir", { id }),
            );
            setUnreadWadir(resp?.data?.data || []);
        } catch (e) {
            console.error("Error fetching unread wadir:", e);
        } finally {
            setLoadingWadir(false);
        }
    };

    const fetchReadTracking = async () => {
        setLoadingReadWadir(true);
        try {
            const resp = await axios.get(
                route("incoming.read_tracking", { id }),
            );
            setReadWadir(resp?.data?.data?.read_wadir || []);
            setDirutRead(resp?.data?.data?.dirut || { read: false });
        } catch (e) {
            console.error("Error fetching read tracking:", e);
        } finally {
            setLoadingReadWadir(false);
        }
    };

    const handleChangeStatus = async () => {
        if (!selectedStatus) {
            message.warning("Pilih status terlebih dahulu");
            return;
        }

        setStatusChangeLoading(true);
        try {
            if (selectedStatus === "READY_DIRUT") {
                await axios.patch(route("incoming.ready_dirut", { id }));
            } else {
                await axios.patch(route("incoming.update", { id }), {
                    mail_number: mail.mail_number,
                    sender: mail.sender,
                    subject: mail.subject,
                    mail_date: dayjs(mail.mail_date).format("YYYY-MM-DD"),
                    received_date: dayjs(mail.received_date).format(
                        "YYYY-MM-DD",
                    ),
                    status_code: selectedStatus,
                    incoming_mail_type_id: mail.incoming_mail_type_id || null,
                });
            }

            message.success("Status surat berhasil diubah");
            const resp = await axios.get(route("incoming.show", { id }));
            setMail(resp.data.data);
            setSelectedStatus(null);
        } catch (e) {
            const errMsg = e.response?.data?.message || "Gagal mengubah status";
            message.error(errMsg);
        } finally {
            setStatusChangeLoading(false);
        }
    };

    /* =======================
       EDIT INCOMING MAIL
    ======================= */
    const openEdit = () => {
        if (!mail) return;
        setEditVisible(true);
    };

    useEffect(() => {
        if (editVisible && mail) {
            form.setFieldsValue({
                mail_number: mail.mail_number,
                sender: mail.sender,
                subject: mail.subject,
                mail_date: mail.mail_date ? dayjs(mail.mail_date) : null,
                received_date: mail.received_date
                    ? dayjs(mail.received_date)
                    : null,
                summary: mail.summary,
                incoming_mail_type_id: mail.incoming_mail_type_id || null,
            });
        }
    }, [editVisible, mail, form]);

    const submitEdit = async (values) => {
        try {
            await axios.patch(route("incoming.update", { id }), {
                ...values,
                mail_date: values.mail_date.format("YYYY-MM-DD"),
                received_date: values.received_date.format("YYYY-MM-DD"),
                incoming_mail_type_id: values.incoming_mail_type_id || null,
            });

            message.success("Data berhasil diperbarui");
            setEditVisible(false);

            const resp = await axios.get(route("incoming.show", { id }));
            setMail(resp.data.data);
        } catch (e) {
            console.error(e);
            message.error("Gagal memperbarui data");
        }
    };

    /* =======================
        DOCUMENT ACTIONS
    ======================= */
    const submitReplace = async (vals) => {
        const fd = new FormData();
        fd.append("file", vals.file.file);

        try {
            await axios.post(route("incoming.replace", { id }), fd);
            message.success("Dokumen berhasil diganti");
            setReplaceVisible(false);
        } catch (e) {
            console.error(e);
            message.error("Gagal mengganti dokumen");
        }
    };

    const openDocEdit = () => {
        docForm.setFieldsValue({ summary: mail.summary });
        setDocEditVisible(true);
    };

    const submitDocEdit = async (vals) => {
        try {
            await axios.patch(route("incoming.edit_document", { id }), vals);
            message.success("Metadata diperbarui");
            setDocEditVisible(false);
        } catch (e) {
            console.error(e);
            message.error("Gagal update metadata");
        }
    };

    /* =======================
        DISPOSITIONS
    ======================= */
    const [dispositions, setDispositions] = useState([]);
    const [loadingDispositions, setLoadingDispositions] = useState(true);
    const [dispModalVisible, setDispModalVisible] = useState(false);
    const [unitsList, setUnitsList] = useState([]);
    const [dispForm] = Form.useForm();
    const [editingDisposition, setEditingDisposition] = useState(null);
    const [resolveVisible, setResolveVisible] = useState(false);
    const [resolveNote, setResolveNote] = useState("");
    const [resolvingDisposition, setResolvingDisposition] = useState(null);
    const [resolveViewVisible, setResolveViewVisible] = useState(false);
    const [resolveViewDisposition, setResolveViewDisposition] = useState(null);
    const [resolveViewImageError, setResolveViewImageError] = useState(false);
    const [shareVisible, setShareVisible] = useState(false);
    const [shareDisposition, setShareDisposition] = useState(null);

    const fetchDispositions = async () => {
        setLoadingDispositions(true);
        try {
            const resp = await axios.get(
                route("incoming.dispositions.index", { id }),
            );
            setDispositions(resp?.data?.data || []);
        } catch (e) {
            console.error("fetchDispositions", e);
        } finally {
            setLoadingDispositions(false);
        }
    };

    useEffect(() => {
        if (id) fetchDispositions();
        // fetch units for disposition target select
        (async () => {
            try {
                const resp = await axios.get(route("units.list"));
                setUnitsList(resp?.data?.data || []);
            } catch (e) {
                console.error("fetchUnits", e);
            }
        })();
    }, [id]);

    const submitDisposition = async (vals) => {
        setDispSubmitting(true);

        try {
            const payload = {
                instruction: vals.instruction,
                due_date: vals.due_date
                    ? dayjs(vals.due_date).format("YYYY-MM-DD")
                    : null,
                to_unit_id: vals.to_unit_id || null,
            };

            if (editingDisposition) {
                await axios.patch(
                    route("incoming.dispositions.update", {
                        id,
                        disposition_id: editingDisposition.id,
                    }),
                    payload,
                );
                message.success("Disposisi diperbarui");
            } else {
                await axios.post(
                    route("incoming.dispositions.store", { id }),
                    payload,
                );
                message.success("Disposisi dibuat");
            }

            setDispModalVisible(false);
            setEditingDisposition(null);
            dispForm.resetFields();
            fetchDispositions();
        } catch (e) {
            console.error(e);
            message.error("Gagal menyimpan disposisi");
        } finally {
            setDispSubmitting(false);
        }
    };

    const openEditDisposition = (d) => {
        setEditingDisposition(d);
        dispForm.setFieldsValue({
            to_unit_id: d.to_unit_id || null,
            instruction: d.instruction || "",
            due_date: d.due_date ? dayjs(d.due_date) : null,
        });
        setDispModalVisible(true);
    };

    const deleteDisposition = async (d) => {
        try {
            await axios.delete(
                route("incoming.dispositions.destroy", {
                    id,
                    disposition_id: d.id,
                }),
            );
            message.success("Disposisi dihapus");
            fetchDispositions();
        } catch (e) {
            console.error(e);
            message.error("Gagal menghapus disposisi");
        }
    };

    const openResolve = (d) => {
        setResolvingDisposition({ ...d, resolveFile: null });
        setResolveNote("");
        setResolveVisible(true);
    };

    const openResolveView = (d) => {
        setResolveViewImageError(false);
        setResolveViewDisposition(d);
        setResolveViewVisible(true);
    };

    const submitResolve = async () => {
        if (!resolvingDisposition) return;
        if (!resolveNote || !resolveNote.trim()) {
            message.warning("Keterangan resolve wajib diisi");
            return;
        }

        setResolveSubmitting(true);

        try {
            const fd = new FormData();
            fd.append("_method", "PATCH");

            if (resolveNote) fd.append("note", resolveNote);
            if (resolvingDisposition?.resolveFile) {
                fd.append("image", resolvingDisposition.resolveFile);
            }

            await axios.post(
                route("dispositions.resolve", { id: resolvingDisposition.id }),
                fd,
                { headers: { "Content-Type": "multipart/form-data" } },
            );

            message.success("Disposisi berhasil di-resolve");

            setResolveVisible(false);
            setResolvingDisposition(null);
            setResolveNote("");
            fetchDispositions();
        } catch (e) {
            const errMsg =
                e.response?.data?.message || "Gagal resolve disposisi";
            message.error(errMsg);
        } finally {
            setResolveSubmitting(false);
        }
    };

    const buildDispositionPdfUrl = (dispositionId, download = false) => {
        const url = route("dispositions.download_pdf", {
            id: dispositionId,
            ...(download ? { download: 1 } : {}),
        });

        if (/^https?:\/\//i.test(url)) return url;
        return `${window.location.origin}${url.startsWith("/") ? "" : "/"}${url}`;
    };

    const openShareDisposition = (d) => {
        setShareDisposition(d);
        setShareVisible(true);
    };

    const handleCopyShareLink = async () => {
        if (!shareDisposition?.id) return;
        const link = buildDispositionPdfUrl(shareDisposition.id);

        try {
            if (navigator?.clipboard?.writeText) {
                await navigator.clipboard.writeText(link);
                message.success("Link berhasil disalin");
                return;
            }
        } catch (_) {}

        const textArea = document.createElement("textarea");
        textArea.value = link;
        textArea.style.position = "fixed";
        textArea.style.opacity = "0";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            document.execCommand("copy");
            message.success("Link berhasil disalin");
        } catch (_) {
            message.error("Gagal menyalin link");
        } finally {
            document.body.removeChild(textArea);
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<p>Detail Surat Masuk</p>}
        >
            <Head title="Detail Surat Masuk" />

            <Card title="Detail Surat">
                {mail && (
                    <>
                        <Descriptions bordered size="small" column={1}>
                            <Descriptions.Item
                                label={
                                    <div style={{ width: 120 }}>No. Surat</div>
                                }
                            >
                                {mail.mail_number}
                            </Descriptions.Item>
                            <Descriptions.Item label={<div>Pengirim</div>}>
                                {mail.sender}
                            </Descriptions.Item>
                            <Descriptions.Item label={<div>Tanggal Surat</div>}>
                                {mail.mail_date
                                    ? dayjs(mail.mail_date).format(
                                          "D MMMM YYYY",
                                      )
                                    : "-"}
                            </Descriptions.Item>
                            <Descriptions.Item
                                label={<div>Tanggal Diterima</div>}
                            >
                                {mail.received_date
                                    ? dayjs(mail.received_date).format(
                                          "D MMMM YYYY",
                                      )
                                    : "-"}
                            </Descriptions.Item>
                            <Descriptions.Item label={<div>Perihal</div>}>
                                {mail.subject}
                            </Descriptions.Item>
                            <Descriptions.Item label={<div>Jenis Surat</div>}>
                                {mail.type?.name || "-"}
                            </Descriptions.Item>
                            <Descriptions.Item label={<div>Status</div>}>
                                {mail.status_code}
                            </Descriptions.Item>
                            <Descriptions.Item label={<div>Ringkasan</div>}>
                                {mail.summary}
                            </Descriptions.Item>
                        </Descriptions>

                        <Row style={{ marginTop: 12 }}>
                            {["superadmin", "admin"].includes(
                                auth.user?.role?.name,
                            ) && (
                                <>
                                    <Button type="primary" onClick={openEdit}>
                                        Edit IncomingMail
                                    </Button>
                                    <Button
                                        onClick={() => setReplaceVisible(true)}
                                        style={{ marginLeft: 8 }}
                                    >
                                        Replace Document
                                    </Button>
                                    <Button
                                        onClick={openDocEdit}
                                        style={{ marginLeft: 8 }}
                                    >
                                        Edit Document
                                    </Button>
                                </>
                            )}
                        </Row>

                        {/* Workflow: Unread Wakil Direksi & Change Status */}

                        {["superadmin", "admin", "wadir"].includes(
                            auth.user?.role?.name,
                        ) && (
                            <Card
                                style={{
                                    marginTop: 16,
                                    backgroundColor: "#f5f5f5",
                                }}
                            >
                                <Row gutter={16}>
                                    {["superadmin", "admin", "wadir"].includes(
                                        auth.user?.role?.name,
                                    ) && (
                                        <Col span={12}>
                                            <h4>Direksi yang Belum Membaca</h4>
                                            {loadingWadir ? (
                                                <p>Loading...</p>
                                            ) : unreadWadir.length === 0 ? (
                                                <p style={{ color: "green" }}>
                                                    ✓ Semua direksi sudah
                                                    membaca
                                                </p>
                                            ) : (
                                                <ul>
                                                    {unreadWadir.map((w) => (
                                                        <li key={w.id}>
                                                            {w.name} ({w.email})
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}

                                            <div style={{ marginTop: 12 }}>
                                                <h4>Status Direktur Utama</h4>
                                                {dirutRead?.read ? (
                                                    <p
                                                        style={{
                                                            color: "green",
                                                        }}
                                                    >
                                                        ✓ Sudah membaca
                                                        {dirutRead.read_at
                                                            ? ` (${dayjs(
                                                                  dirutRead.read_at,
                                                              ).format(
                                                                  "D MMMM YYYY",
                                                              )})`
                                                            : ""}
                                                    </p>
                                                ) : (
                                                    <p
                                                        style={{
                                                            color: "#999",
                                                        }}
                                                    >
                                                        Belum membaca
                                                    </p>
                                                )}
                                            </div>
                                        </Col>
                                    )}
                                    <Col span={12}>
                                        {["superadmin", "admin"].includes(
                                            auth.user?.role?.name,
                                        ) && (
                                            <>
                                                <h4>Ubah Status Surat</h4>
                                                <Row
                                                    gutter={8}
                                                    style={{ marginBottom: 8 }}
                                                >
                                                    <Col span={16}>
                                                        <Select
                                                            placeholder="Pilih status baru"
                                                            value={
                                                                selectedStatus
                                                            }
                                                            onChange={
                                                                setSelectedStatus
                                                            }
                                                            style={{
                                                                width: "100%",
                                                            }}
                                                        >
                                                            {statuses.map(
                                                                (s) => (
                                                                    <Select.Option
                                                                        key={
                                                                            s.code
                                                                        }
                                                                        value={
                                                                            s.code
                                                                        }
                                                                    >
                                                                        {s.name}
                                                                    </Select.Option>
                                                                ),
                                                            )}
                                                        </Select>
                                                    </Col>
                                                    <Col span={8}>
                                                        <Popconfirm
                                                            title="Ubah status surat?"
                                                            okText="Ya"
                                                            cancelText="Batal"
                                                            onConfirm={
                                                                handleChangeStatus
                                                            }
                                                            disabled={
                                                                statusChangeLoading
                                                            }
                                                        >
                                                            <Button
                                                                type="primary"
                                                                loading={
                                                                    statusChangeLoading
                                                                }
                                                                style={{
                                                                    width: "100%",
                                                                }}
                                                            >
                                                                Update Status
                                                            </Button>
                                                        </Popconfirm>
                                                    </Col>
                                                </Row>
                                                <p
                                                    style={{
                                                        fontSize: 12,
                                                        color: "#999",
                                                    }}
                                                >
                                                    Status saat ini:{" "}
                                                    <strong>
                                                        {mail.status_code}
                                                    </strong>
                                                </p>
                                            </>
                                        )}
                                    </Col>
                                </Row>
                            </Card>
                        )}
                    </>
                )}
            </Card>

            <Card
                title="Riwayat Disposisi"
                style={{ marginTop: 12 }}
                extra={
                    ["dirut", "wadir"].includes(auth.user?.role?.name) && (
                        <Button
                            icon={<PlusOutlined />}
                            type="primary"
                            onClick={() => {
                                setEditingDisposition(null);
                                dispForm.resetFields();
                                setDispModalVisible(true);
                            }}
                        >
                            Buat Disposisi
                        </Button>
                    )
                }
            >
                {(() => {
                    // Filter dispositions based on user role
                    const canViewAll = [
                        "superadmin",
                        "admin",
                        "dirut",
                        "wadir",
                    ].includes(auth.user?.role?.name);
                    const filteredDispositions = canViewAll
                        ? dispositions
                        : dispositions.filter(
                              (d) => d.to_unit_id === auth.user?.unit_id,
                          );

                    if (loadingDispositions) {
                        return (
                            <div style={{ padding: "12px 0" }}>
                                <Spin />
                            </div>
                        );
                    }

                    return filteredDispositions.length === 0 ? (
                        <p>Belum ada disposisi</p>
                    ) : (
                        <div
                            style={{
                                display: "flex",
                                flexDirection: "column",
                                gap: 16,
                            }}
                        >
                            {filteredDispositions.map((d) => (
                                <div
                                    key={d.id}
                                    style={{
                                        display: "flex",
                                        gap: 12,
                                        alignItems: "flex-start",
                                    }}
                                >
                                    <span
                                        style={{
                                            width: 10,
                                            height: 10,
                                            borderRadius: "50%",
                                            backgroundColor:
                                                d.status === "resolved"
                                                    ? "#52c41a"
                                                    : "#faad14",
                                            marginTop: 6,
                                            flexShrink: 0,
                                        }}
                                    />
                                    <div
                                        style={{
                                            display: "flex",
                                            flexDirection: "column",
                                            gap: 8,
                                            flex: 1,
                                        }}
                                    >
                                        <div
                                            style={{
                                                display: "flex",
                                                alignItems: "center",
                                                gap: 8,
                                                flexWrap: "wrap",
                                            }}
                                        >
                                            <strong>
                                                <>
                                                    {d.unit?.name ||
                                                        d.to_unit ||
                                                        "-"}
                                                </>
                                            </strong>

                                            <Tag
                                                color={
                                                    d.status === "resolved"
                                                        ? "green"
                                                        : "orange"
                                                }
                                            >
                                                {d.status === "resolved"
                                                    ? "Resolved"
                                                    : "Open"}
                                            </Tag>
                                        </div>

                                        <div
                                            style={{
                                                fontSize: 12,
                                                color: "#666",
                                                display: "grid",
                                                gridTemplateColumns:
                                                    "120px 1fr",
                                                rowGap: 4,
                                                marginTop: 6,
                                            }}
                                        >
                                            <div>Dibuat Oleh</div>
                                            <div>
                                                {d.from_user_name ||
                                                    d.fromUser?.name ||
                                                    "-"}
                                            </div>
                                            <div>Tanggal Dibuat</div>
                                            <div>
                                                {d.created_at
                                                    ? dayjs(
                                                          d.created_at,
                                                      ).format("D MMMM YYYY")
                                                    : "-"}
                                            </div>
                                            <div>Jatuh Tempo</div>
                                            <div>
                                                {d.due_date
                                                    ? dayjs(d.due_date).format(
                                                          "D MMMM YYYY",
                                                      )
                                                    : "-"}
                                            </div>
                                            <div>Instruksi</div>
                                            <div>{d.instruction}</div>
                                            <div>Status Baca Unit</div>
                                            <div>
                                                <Tag
                                                    color={
                                                        d.is_unit_read
                                                            ? "green"
                                                            : "default"
                                                    }
                                                >
                                                    {d.is_unit_read
                                                        ? "Sudah Dibuka/Diunduh"
                                                        : "Belum Dibuka"}
                                                </Tag>
                                            </div>
                                        </div>

                                        {d.status === "resolved" && (
                                            <div
                                                style={{
                                                    fontSize: 12,
                                                    color: "#444",
                                                    background: "#fafafa",
                                                    border: "1px solid #eee",
                                                    borderRadius: 6,
                                                    padding: 8,
                                                    marginTop: 8,
                                                }}
                                            >
                                                <div
                                                    style={{ marginBottom: 4 }}
                                                >
                                                    <strong>
                                                        Hasil Resolve
                                                    </strong>
                                                </div>
                                                <div>
                                                    Keterangan Resolve:{" "}
                                                    {d.resolved_note || "-"}
                                                </div>
                                                <div>
                                                    Tanggal Resolve:{" "}
                                                    {d.resolved_at
                                                        ? dayjs(
                                                              d.resolved_at,
                                                          ).format(
                                                              "D MMMM YYYY",
                                                          )
                                                        : "-"}
                                                </div>
                                                <div>
                                                    Lampiran:{" "}
                                                    {d.resolved_image_path
                                                        ? "Ada"
                                                        : "Tidak ada"}
                                                </div>
                                            </div>
                                        )}

                                        <div
                                            style={{
                                                display: "flex",
                                                gap: 8,
                                                flexWrap: "wrap",
                                            }}
                                        >
                                            {(auth.user?.id ===
                                                d.from_user_id ||
                                                auth.user?.role?.name ===
                                                    "superadmin") && (
                                                <>
                                                    <Button
                                                        type="link"
                                                        onClick={() =>
                                                            openEditDisposition(
                                                                d,
                                                            )
                                                        }
                                                        disabled={
                                                            d.status ===
                                                            "resolved"
                                                        }
                                                    >
                                                        Edit
                                                    </Button>
                                                    <Popconfirm
                                                        title="Hapus disposisi ini?"
                                                        onConfirm={() =>
                                                            deleteDisposition(d)
                                                        }
                                                        okText="Ya"
                                                        cancelText="Tidak"
                                                        disabled={
                                                            d.status ===
                                                            "resolved"
                                                        }
                                                    >
                                                        <Button
                                                            type="link"
                                                            danger
                                                            disabled={
                                                                d.status ===
                                                                "resolved"
                                                            }
                                                        >
                                                            Delete
                                                        </Button>
                                                    </Popconfirm>
                                                </>
                                            )}
                                            <Button
                                                type="link"
                                                icon={<ShareAltOutlined />}
                                                onClick={() =>
                                                    openShareDisposition(d)
                                                }
                                            >
                                                Bagikan
                                            </Button>
                                            {auth.user?.unit_id &&
                                                d.to_unit_id ===
                                                    auth.user?.unit_id &&
                                                d.status !== "resolved" && (
                                                    <Button
                                                        type="link"
                                                        onClick={() =>
                                                            openResolve(d)
                                                        }
                                                    >
                                                        Resolve
                                                    </Button>
                                                )}
                                            {d.status === "resolved" && (
                                                <Button
                                                    type="link"
                                                    onClick={() =>
                                                        openResolveView(d)
                                                    }
                                                >
                                                    Lihat Resolve
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    );
                })()}
            </Card>

            <Card title="Preview Dokumen" style={{ marginTop: 12 }}>
                {mail?.file_path ? (
                    <iframe
                        src={route("incoming.preview", { id: mail.id })}
                        style={{ width: "100%", height: 600 }}
                    />
                ) : (
                    <p>Tidak ada dokumen</p>
                )}
            </Card>

            {/* EDIT MODAL */}
            <Modal
                open={editVisible}
                title="Edit Surat Masuk"
                destroyOnHidden
                onCancel={() => {
                    form.resetFields();
                    setEditVisible(false);
                }}
                footer={null}
            >
                <Form form={form} layout="vertical" onFinish={submitEdit}>
                    <Form.Item
                        name="mail_number"
                        label="No. Surat"
                        rules={[{ required: true }]}
                    >
                        <Input />
                    </Form.Item>
                    <Form.Item
                        name="sender"
                        label="Pengirim"
                        rules={[{ required: true }]}
                    >
                        <Input />
                    </Form.Item>
                    <Form.Item
                        name="subject"
                        label="Perihal"
                        rules={[{ required: true }]}
                    >
                        <Input />
                    </Form.Item>
                    <Form.Item
                        name="mail_date"
                        label="Tanggal Surat"
                        rules={[{ required: true }]}
                    >
                        <DatePicker style={{ width: "100%" }} />
                    </Form.Item>
                    <Form.Item
                        name="received_date"
                        label="Tanggal Diterima"
                        rules={[{ required: true }]}
                    >
                        <DatePicker style={{ width: "100%" }} />
                    </Form.Item>
                    <Form.Item
                        name="incoming_mail_type_id"
                        label="Jenis Surat"
                        rules={[{ required: true, message: "Jenis surat wajib dipilih" }]}
                    >
                        <Select
                            placeholder="Pilih jenis surat"
                            options={mailTypes.map((type) => ({
                                label: type.name,
                                value: type.id,
                            }))}
                        />
                    </Form.Item>
                    <Form.Item name="summary" label="Ringkasan">
                        <Input.TextArea rows={4} />
                    </Form.Item>
                    <Form.Item style={{ textAlign: "right" }}>
                        <Button type="primary" htmlType="submit">
                            Simpan
                        </Button>
                    </Form.Item>
                </Form>
            </Modal>

            {/* REPLACE DOCUMENT */}
            <Modal
                open={replaceVisible}
                title="Replace Document"
                onCancel={() => setReplaceVisible(false)}
                footer={null}
            >
                <Form onFinish={submitReplace} layout="vertical">
                    <Form.Item
                        name="file"
                        label="File"
                        rules={[{ required: true }]}
                        getValueFromEvent={(e) => e}
                    >
                        <Upload
                            beforeUpload={() => false}
                            maxCount={1}
                            accept="application/pdf"
                        >
                            <Button icon={<UploadOutlined />}>
                                Pilih File
                            </Button>
                        </Upload>
                    </Form.Item>
                    <Form.Item style={{ textAlign: "right" }}>
                        <Button type="primary" htmlType="submit">
                            Replace
                        </Button>
                    </Form.Item>
                </Form>
            </Modal>

            {/* DISPOSITION MODAL */}
            <Modal
                open={dispModalVisible}
                title={editingDisposition ? "Edit Disposisi" : "Buat Disposisi"}
                onCancel={() => {
                    setDispModalVisible(false);
                    setEditingDisposition(null);
                    dispForm.resetFields();
                }}
                closable={false}
                maskClosable={false}
                footer={null}
            >
                <Form
                    form={dispForm}
                    layout="vertical"
                    onFinish={submitDisposition}
                >
                    <Form.Item
                        name="to_unit_id"
                        label="Tujuan (Unit)"
                        rules={[
                            { required: true, message: "Pilih unit tujuan" },
                        ]}
                    >
                        <Select placeholder="Pilih unit">
                            {unitsList.map((u) => (
                                <Select.Option key={u.id} value={u.id}>
                                    {u.name} ({u.code})
                                </Select.Option>
                            ))}
                        </Select>
                    </Form.Item>
                    <Form.Item
                        name="instruction"
                        label="Instruksi"
                        rules={[{ required: true }]}
                    >
                        <Input.TextArea rows={4} />
                    </Form.Item>
                    <Form.Item name="due_date" label="Tanggal Jatuh Tempo">
                        <DatePicker style={{ width: "100%" }} />
                    </Form.Item>
                    <Form.Item style={{ textAlign: "right" }}>
                        <Button
                            style={{ marginRight: 5 }}
                            loading={dispSubmitting}
                            disabled={dispSubmitting}
                            onClick={() => {
                                setDispModalVisible(false);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="primary"
                            htmlType="submit"
                            loading={dispSubmitting}
                            disabled={dispSubmitting}
                        >
                            {editingDisposition ? "Update" : "Buat"}
                        </Button>
                    </Form.Item>
                </Form>
            </Modal>

            {/* EDIT DOCUMENT */}
            <Modal
                open={docEditVisible}
                title="Edit Document"
                onCancel={() => setDocEditVisible(false)}
                footer={null}
            >
                <Form form={docForm} layout="vertical" onFinish={submitDocEdit}>
                    <Form.Item name="summary" label="Ringkasan">
                        <Input.TextArea rows={4} />
                    </Form.Item>
                    <Form.Item style={{ textAlign: "right" }}>
                        <Button type="primary" htmlType="submit">
                            Simpan
                        </Button>
                    </Form.Item>
                </Form>
            </Modal>

            {/* RESOLVE DISPOSITION */}
            <Modal
                open={resolveVisible}
                title="Resolve Disposisi"
                width={520}
                closable={false}
                maskClosable={false}
                keyboard={false}
                footer={[
                    <Button
                        key="cancel"
                        disabled={resolveSubmitting}
                        onClick={() => {
                            setResolveVisible(false);
                            setResolvingDisposition(null);
                            setResolveNote("");
                        }}
                    >
                        Batal
                    </Button>,

                    <Popconfirm
                        key="confirm"
                        title="Resolve disposisi ini?"
                        description="Status akan diubah menjadi resolved"
                        okText="Ya"
                        cancelText="Tidak"
                        onConfirm={submitResolve}
                        disabled={resolveSubmitting}
                    >
                        <Button type="primary" loading={resolveSubmitting}>
                            Resolve
                        </Button>
                    </Popconfirm>,
                ]}
            >
                <Form layout="vertical">
                    <Form.Item
                        label="Keterangan"
                        required
                        validateStatus={!resolveNote ? "error" : ""}
                        help={!resolveNote ? "Keterangan wajib diisi" : ""}
                    >
                        <Input.TextArea
                            rows={3}
                            value={resolveNote}
                            onChange={(e) => setResolveNote(e.target.value)}
                            placeholder="Wajib diisi keterangan resolve"
                        />
                    </Form.Item>

                    <Form.Item label="Upload Lampiran (opsional)">
                        <Upload
                            beforeUpload={(file) => {
                                const allowedTypes = [
                                    "image/jpeg",
                                    "image/jpg",
                                    "image/png",
                                    "image/webp",
                                    "application/pdf",
                                ];
                                if (!allowedTypes.includes(file.type)) {
                                    message.error(
                                        "Hanya file gambar (JPG/PNG/WEBP) atau PDF yang diperbolehkan",
                                    );
                                    return Upload.LIST_IGNORE;
                                }

                                setResolvingDisposition((prev) => ({
                                    ...(prev || {}),
                                    resolveFile: file,
                                }));
                                return false;
                            }}
                            maxCount={1}
                            accept="image/*,application/pdf"
                            fileList={
                                resolvingDisposition?.resolveFile
                                    ? [resolvingDisposition.resolveFile]
                                    : []
                            }
                        >
                            <Button icon={<UploadOutlined />}>
                                Pilih Gambar
                            </Button>
                        </Upload>
                    </Form.Item>
                </Form>
            </Modal>

            {/* RESOLVE VIEW MODAL */}
            <Modal
                open={resolveViewVisible}
                title="Detail Resolve Disposisi"
                onCancel={() => {
                    setResolveViewVisible(false);
                    setResolveViewDisposition(null);
                    setResolveViewImageError(false);
                }}
                footer={null}
            >
                {resolveViewDisposition ? (
                    <div>
                        <div style={{ marginBottom: 8 }}>
                            <strong>Instruksi:</strong>{" "}
                            {resolveViewDisposition.instruction || "-"}
                        </div>
                        <div style={{ marginBottom: 8 }}>
                            <strong>Status:</strong>{" "}
                            {resolveViewDisposition.status || "open"}
                        </div>
                        <div style={{ marginBottom: 8 }}>
                            <strong>Tanggal Resolve:</strong>{" "}
                            {resolveViewDisposition.resolved_at
                                ? dayjs(
                                      resolveViewDisposition.resolved_at,
                                  ).format("D MMMM YYYY")
                                : "-"}
                        </div>
                        <div style={{ marginBottom: 8 }}>
                            <strong>Keterangan Resolve:</strong>{" "}
                            {resolveViewDisposition.resolved_note || "-"}
                        </div>
                        {resolveViewDisposition.resolved_image_path ? (
                            <div>
                                <strong>Lampiran:</strong>
                                <div style={{ marginTop: 8 }}>
                                    {(() => {
                                        const path =
                                            resolveViewDisposition.resolved_image_path ||
                                            "";
                                        const isPdf = path
                                            .toLowerCase()
                                            .endsWith(".pdf");
                                        const fileUrl = route(
                                            "dispositions.resolve_file",
                                            {
                                                id: resolveViewDisposition.id,
                                            },
                                        );
                                        const downloadUrl = route(
                                            "dispositions.resolve_file",
                                            {
                                                id: resolveViewDisposition.id,
                                                download: 1,
                                            },
                                        );

                                        return isPdf ? (
                                            <>
                                                <Button
                                                    type="link"
                                                    href={fileUrl}
                                                    target="_blank"
                                                >
                                                    Buka PDF
                                                </Button>
                                                <Button
                                                    type="link"
                                                    href={downloadUrl}
                                                    target="_blank"
                                                >
                                                    Simpan PDF
                                                </Button>
                                            </>
                                        ) : resolveViewImageError ? (
                                            <p style={{ color: "#999" }}>
                                                Gagal memuat gambar
                                            </p>
                                        ) : (
                                            <>
                                                <img
                                                    src={fileUrl}
                                                    alt="Lampiran resolve"
                                                    onError={() =>
                                                        setResolveViewImageError(
                                                            true,
                                                        )
                                                    }
                                                    style={{
                                                        width: "100%",
                                                        maxHeight: 360,
                                                        objectFit: "contain",
                                                        borderRadius: 6,
                                                        border: "1px solid #eee",
                                                    }}
                                                />
                                                <div>
                                                    <Button
                                                        type="link"
                                                        href={downloadUrl}
                                                        target="_blank"
                                                    >
                                                        Simpan Gambar
                                                    </Button>
                                                </div>
                                            </>
                                        );
                                    })()}
                                </div>
                            </div>
                        ) : null}
                    </div>
                ) : (
                    <p>Data tidak tersedia</p>
                )}
            </Modal>

            <Modal
                open={shareVisible}
                title="Bagikan Disposisi Dalam Bentuk PDF"
                onCancel={() => {
                    setShareVisible(false);
                    setShareDisposition(null);
                }}
                footer={null}
                width={360}
            >
                <Input
                    size="small"
                    readOnly
                    value={
                        shareDisposition?.id
                            ? buildDispositionPdfUrl(shareDisposition.id)
                            : ""
                    }
                />
                <div
                    style={{
                        marginTop: 10,
                        display: "flex",
                        gap: 8,
                        justifyContent: "flex-end",
                    }}
                >
                    <Button
                        size="small"
                        icon={<CopyOutlined />}
                        onClick={handleCopyShareLink}
                    >
                        Copy Link
                    </Button>
                    <Button
                        size="small"
                        type="primary"
                        icon={<DownloadOutlined />}
                        href={
                            shareDisposition?.id
                                ? buildDispositionPdfUrl(
                                      shareDisposition.id,
                                      true,
                                  )
                                : undefined
                        }
                        target="_blank"
                    >
                        Download
                    </Button>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
