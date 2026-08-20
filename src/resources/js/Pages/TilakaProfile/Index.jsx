import React, { useState, useEffect } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import {
    Card,
    Form,
    Input,
    Button,
    Alert,
    Row,
    Col,
    Spin,
    Modal,
    Divider,
    Tag,
    notification,
} from "antd";
import { PlusOutlined } from "@ant-design/icons";
import axios from "axios";
import ProfileFormModal from "./ProfileFormModal";
import UploadDocumentModal from "./UploadDocumentModal";

export default function TilakaProfilePage({ auth }) {
    const [form] = Form.useForm();
    const [profile, setProfile] = useState(null);
    const [loading, setLoading] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [previewOpen, setPreviewOpen] = useState(false);
    const [previewImage, setPreviewImage] = useState("");
    const [previewTitle, setPreviewTitle] = useState("");

    // Tilaka status (SOURCE OF TRUTH)
    const [tilakaStatus, setTilakaStatus] = useState(null);
    const [tilakaLoading, setTilakaLoading] = useState(false);

    // Modal states
    const [isProfileModalOpen, setIsProfileModalOpen] = useState(false);
    const [isUploadKtpModalOpen, setIsUploadKtpModalOpen] = useState(false);
    const [isUploadSelfieModalOpen, setIsUploadSelfieModalOpen] =
        useState(false);
    const [isUploadSignatureModalOpen, setIsUploadSignatureModalOpen] =
        useState(false);
    const [editingMode, setEditingMode] = useState("create");

    useEffect(() => {
        fetchProfile();
    }, []);

    useEffect(() => {
        if (profile?.tilaka_uuid) {
            fetchTilakaStatus();
        } else {
            setTilakaStatus(null);
        }
    }, [profile]);

    const fetchProfile = async () => {
        setLoading(true);
        try {
            const res = await axios.get(route("tilaka.profile.show"));
            setProfile(res?.data?.data || null);
        } catch {
            setProfile(null);
        } finally {
            setLoading(false);
        }
    };

    const fetchTilakaStatus = async () => {
        setTilakaLoading(true);
        try {
            const res = await axios.get(route("tilaka.profile.userregstatus"));
            setTilakaStatus(res?.data?.data ?? null);
        } catch {
            setTilakaStatus(null);
        } finally {
            setTilakaLoading(false);
        }
    };

    const tilakaCode = tilakaStatus?.status ?? null; // D | B | S | F | E | null

    // ===== REPLACE FULL verification_status LOGIC =====
    const canEditProfile =
        tilakaCode === null ||
        tilakaCode === "D" ||
        tilakaCode === "F" ||
        tilakaCode === "E";
    const canUploadDocument = canEditProfile;
    const canSubmitKyc =
        tilakaCode === null ||
        tilakaCode === "D" ||
        tilakaCode === "F" ||
        tilakaCode === "E";
    const canLiveness = tilakaCode === "B";
    const isVerified = tilakaCode === "S";

    const onFinish = async (values) => {
        try {
            const res = await axios.post(route("tilaka.profile.store"), values);
            setProfile(res?.data?.data);
            setIsProfileModalOpen(false);
            notification.success({
                message:
                    editingMode === "create"
                        ? "Profil Tilaka berhasil dibuat"
                        : "Profil Tilaka berhasil diperbarui",
            });
            window.location.reload();
        } catch (error) {
            notification.error({
                message:
                    error.response?.data?.message || "Gagal menyimpan profil",
            });
        }
    };

    const handleUploadDocument = async (file, documentType) => {
        setUploading(true);
        const formData = new FormData();
        formData.append("file", file);
        formData.append("document_type", documentType);

        try {
            const res = await axios.post(
                route("tilaka.profile.upload"),
                formData,
                { headers: { "Content-Type": "multipart/form-data" } },
            );
            setProfile(res?.data?.data);

            if (documentType === "ktp") setIsUploadKtpModalOpen(false);
            if (documentType === "selfie") setIsUploadSelfieModalOpen(false);
            if (documentType === "signature")
                setIsUploadSignatureModalOpen(false);

            notification.success({
                message: (() => {
                    if (documentType === "ktp") return "KTP berhasil diunggah";
                    if (documentType === "selfie")
                        return "Selfie berhasil diunggah";
                    if (documentType === "signature")
                        return "Tanda tangan berhasil diunggah";
                    return "Dokumen berhasil diunggah";
                })(),
            });
        } catch (error) {
            notification.error({
                message:
                    error.response?.data?.message || "Gagal mengunggah dokumen",
            });
        } finally {
            setUploading(false);
        }
    };

    const handleSubmitProfile = async () => {
        setSubmitting(true);
        try {
            await axios.post(route("tilaka.profile.submit"));
            notification.success({
                message: "KYC berhasil dikirim",
            });
            window.location.reload();
        } catch (error) {
            notification.error({
                message: error.response?.data?.message || "Gagal submit KYC",
            });
        } finally {
            setSubmitting(false);
        }
    };

    const previewDocumentImage = (documentType) => {
        if (!profile) return;
        const filePath =
            documentType === "ktp"
                ? profile.photo_ktp_path
                : documentType === "selfie"
                  ? profile.selfie_path
                  : profile.signature_path;
        if (!filePath) return;

        setPreviewImage(route("tilaka.profile.preview", { documentType }));
        setPreviewTitle(`Preview ${documentType.toUpperCase()}`);
        setPreviewOpen(true);
    };

    const handleOpenProfileModal = () => {
        setEditingMode(profile ? "edit" : "create");
        setIsProfileModalOpen(true);
    };

    if (loading) {
        return (
            <AuthenticatedLayout user={auth.user}>
                <Spin size="large" />
            </AuthenticatedLayout>
        );
    }

    return (
        <AuthenticatedLayout user={auth.user} header={<p>Profil Tilaka</p>}>
            <Head title="Profil Tilaka" />

            <Row gutter={16}>
                <Col span={24}>
                    <Card title="Profil Tilaka untuk Verifikasi Identitas">
                        {/* STATUS */}
                        {tilakaLoading ? (
                            <div style={{ marginBottom: 20 }}>
                                <strong>Status Verifikasi: </strong>
                                <Spin size="small" />
                            </div>
                        ) : (
                            <div style={{ marginBottom: 20 }}>
                                <strong>Status Verifikasi: </strong>
                                <Tag color="blue">
                                    {tilakaCode === null && "Belum Submit KYC"}
                                    {tilakaCode === "D" && "Belum Submit KYC"}
                                    {tilakaCode === "B" &&
                                        "KYC Dikirim – Liveness Diperlukan"}
                                    {tilakaCode === "S" &&
                                        "Verifikasi Berhasil"}
                                    {tilakaCode === "F" && "Verifikasi Gagal"}
                                    {tilakaCode === "E" && "Error Dukcapil"}
                                </Tag>
                            </div>
                        )}

                        <Divider />

                        {/* PROFILE BELUM ADA */}
                        {!profile && (
                            <div style={{ textAlign: "center", padding: 40 }}>
                                <Alert
                                    message="Profil Tilaka Belum Dibuat"
                                    description="Silakan buat profil Tilaka terlebih dahulu."
                                    type="info"
                                    showIcon
                                    style={{ marginBottom: 20 }}
                                />
                                <Button
                                    type="primary"
                                    size="large"
                                    icon={<PlusOutlined />}
                                    onClick={handleOpenProfileModal}
                                >
                                    Tambah Profil
                                </Button>
                            </div>
                        )}

                        {/* PROFILE ADA */}
                        {profile && (
                            <>
                                <Row gutter={[16, 16]}>
                                    <Col span={12}>
                                        <strong>NIK</strong>
                                        <p>{profile.nik || "-"}</p>
                                    </Col>
                                    <Col span={12}>
                                        <strong>Nama Lengkap</strong>
                                        <p>{profile.full_name || "-"}</p>
                                    </Col>
                                </Row>

                                <Row gutter={[16, 16]}>
                                    <Col span={12}>
                                        <strong>Email</strong>
                                        <p>{profile.email || "-"}</p>
                                    </Col>
                                    <Col span={12}>
                                        <strong>No. Telepon</strong>
                                        <p>{profile.phone || "-"}</p>
                                    </Col>
                                </Row>
                                
                                <Row gutter={[16, 16]}>
                                    <Col span={12}>
                                        <strong>Tilaka UUID</strong>
                                        <p>{profile?.tilaka_uuid || "-"}</p>
                                    </Col>
                                    <Col span={12}>
                                        <strong>Tilaka Username</strong>
                                        <p>{tilakaStatus?.tilaka_name || "-"}</p>
                                    </Col>
                                </Row>

                                <div style={{ margin: "20px 0" }}>
                                    <Button
                                        onClick={handleOpenProfileModal}
                                        disabled={!canEditProfile}
                                    >
                                        Edit Profil
                                    </Button>
                                </div>

                                <Divider />

                                <h3>Status Upload Dokumen</h3>
                                <Row gutter={16}>
                                    <Col span={12}>
                                        <Card size="small" title="Foto KTP">
                                            <div style={{ marginBottom: 12 }}>
                                                {profile.photo_ktp_path ? (
                                                    <Tag color="success">
                                                        ✓ Sudah Terupload
                                                    </Tag>
                                                ) : (
                                                    <Tag color="error">
                                                        ✗ Belum Terupload
                                                    </Tag>
                                                )}
                                            </div>

                                            {profile.photo_ktp_path && (
                                                <Button
                                                    onClick={() =>
                                                        previewDocumentImage(
                                                            "ktp",
                                                        )
                                                    }
                                                    style={{ marginRight: 8 }}
                                                >
                                                    Preview
                                                </Button>
                                            )}

                                            <Button
                                                onClick={() =>
                                                    setIsUploadKtpModalOpen(
                                                        true,
                                                    )
                                                }
                                                disabled={!canUploadDocument}
                                            >
                                                {profile.photo_ktp_path
                                                    ? "Ubah"
                                                    : "Upload"}
                                            </Button>
                                        </Card>
                                    </Col>

                                    <Col span={12}>
                                        <Card size="small" title="Selfie">
                                            <div style={{ marginBottom: 12 }}>
                                                {profile.selfie_path ? (
                                                    <Tag color="success">
                                                        ✓ Sudah Terupload
                                                    </Tag>
                                                ) : (
                                                    <Tag color="error">
                                                        ✗ Belum Terupload
                                                    </Tag>
                                                )}
                                            </div>

                                            {profile.selfie_path && (
                                                <Button
                                                    onClick={() =>
                                                        previewDocumentImage(
                                                            "selfie",
                                                        )
                                                    }
                                                    style={{ marginRight: 8 }}
                                                >
                                                    Preview
                                                </Button>
                                            )}

                                            <Button
                                                onClick={() =>
                                                    setIsUploadSelfieModalOpen(
                                                        true,
                                                    )
                                                }
                                                disabled={!canUploadDocument}
                                            >
                                                {profile.selfie_path
                                                    ? "Ubah"
                                                    : "Upload"}
                                            </Button>
                                        </Card>
                                    </Col>
                                </Row>

                                <Divider />

                                {canSubmitKyc && (
                                    <div style={{ textAlign: "right" }}>
                                        <Button
                                            type="primary"
                                            size="large"
                                            onClick={handleSubmitProfile}
                                            loading={submitting}
                                        >
                                            Submit KYC
                                        </Button>
                                    </div>
                                )}

                                <div style={{ marginTop: 16 }}>
                                    <Card size="small" title="File Tanda Tangan">
                                        <div style={{ marginBottom: 12 }}>
                                            {profile.signature_path ? (
                                                <Tag color="success">
                                                    ✓ Sudah Terupload
                                                </Tag>
                                            ) : (
                                                <Tag color="error">
                                                    ✕ Belum Terupload
                                                </Tag>
                                            )}
                                        </div>

                                        {profile.signature_path && (
                                            <Button
                                                onClick={() =>
                                                    previewDocumentImage(
                                                        "signature",
                                                    )
                                                }
                                                style={{ marginRight: 8 }}
                                            >
                                                Preview
                                            </Button>
                                        )}

                                        <Button
                                            onClick={() =>
                                                setIsUploadSignatureModalOpen(
                                                    true,
                                                )
                                            }
                                            disabled={!canUploadDocument}
                                        >
                                            {profile.signature_path
                                                ? "Ubah"
                                                : "Upload"}
                                        </Button>
                                    </Card>
                                </div>

                                {canLiveness && (
                                    <div
                                        style={{
                                            textAlign: "right",
                                            marginTop: 16,
                                        }}
                                    >
                                        <Button
                                            type="primary"
                                            size="large"
                                            onClick={() =>
                                                (window.location.href = route(
                                                    "tilaka.liveness.start",
                                                ))
                                            }
                                        >
                                            Mulai Liveness
                                        </Button>
                                    </div>
                                )}

                                {isVerified && (
                                    <Alert
                                        type="success"
                                        showIcon
                                        message="Identitas telah terverifikasi"
                                        style={{ marginTop: 20 }}
                                    />
                                )}
                            </>
                        )}
                    </Card>
                </Col>
            </Row>

            {/* MODALS */}
            <ProfileFormModal
                open={isProfileModalOpen}
                onClose={() => setIsProfileModalOpen(false)}
                onFinish={onFinish}
                profile={profile}
                mode={editingMode}
                loading={uploading || submitting}
            />

            <UploadDocumentModal
                open={isUploadKtpModalOpen}
                onClose={() => setIsUploadKtpModalOpen(false)}
                onUpload={(file) => handleUploadDocument(file, "ktp")}
                documentType="ktp"
                loading={uploading}
            />

            <UploadDocumentModal
                open={isUploadSelfieModalOpen}
                onClose={() => setIsUploadSelfieModalOpen(false)}
                onUpload={(file) => handleUploadDocument(file, "selfie")}
                documentType="selfie"
                loading={uploading}
            />

            <UploadDocumentModal
                open={isUploadSignatureModalOpen}
                onClose={() => setIsUploadSignatureModalOpen(false)}
                onUpload={(file) => handleUploadDocument(file, "signature")}
                documentType="signature"
                loading={uploading}
            />

            <Modal
                title={previewTitle}
                open={previewOpen}
                onCancel={() => setPreviewOpen(false)}
                footer={null}
            >
                <img
                    alt={previewTitle}
                    src={previewImage}
                    style={{ width: "100%" }}
                />
            </Modal>
        </AuthenticatedLayout>
    );
}
