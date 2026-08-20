import React, { useEffect } from "react";
import { Modal, Form, Input, Button } from "antd";

export default function ProfileFormModal({
    open,
    onClose,
    onFinish,
    profile,
    mode = "create",
    loading = false,
}) {
    const [form] = Form.useForm();

    // Set form values when modal opens with existing profile
    useEffect(() => {
        if (open && profile && mode === "edit") {
            form.setFieldsValue({
                nik: profile.nik,
                full_name: profile.full_name,
                email: profile.email,
                phone: profile.phone,
            });
        } else if (open && mode === "create") {
            form.resetFields();
        }
    }, [open, profile, mode, form]);

    const handleCancel = () => {
        form.resetFields();
        onClose();
    };

    const handleSubmit = async () => {
        try {
            const values = await form.validateFields();
            onFinish(values);
        } catch (error) {
            // Validation failed, errors are shown in form
        }
    };

    const title =
        mode === "create" ? "Tambah Profil Tilaka" : "Edit Profil Tilaka";

    return (
        <Modal
            title={title}
            open={open}
            onCancel={handleCancel}
            width={500}
            footer={[
                <Button key="cancel" onClick={handleCancel} disabled={loading}>
                    Batal
                </Button>,
                <Button
                    key="submit"
                    type="primary"
                    loading={loading}
                    onClick={handleSubmit}
                >
                    Simpan
                </Button>,
            ]}
        >
            <Form
                form={form}
                layout="vertical"
                autoComplete="off"
            >
                <Form.Item
                    label="NIK"
                    name="nik"
                    rules={[
                        {
                            required: true,
                            message: "NIK harus diisi",
                        },
                        {
                            pattern: /^\d{16}$/,
                            message: "NIK harus 16 digit angka",
                        },
                    ]}
                >
                    <Input
                        placeholder="16 digit NIK"
                        maxLength={16}
                        type="text"
                    />
                </Form.Item>

                <Form.Item
                    label="Nama Lengkap"
                    name="full_name"
                    rules={[
                        {
                            required: true,
                            message: "Nama lengkap harus diisi",
                        },
                    ]}
                >
                    <Input placeholder="Nama lengkap sesuai KTP" />
                </Form.Item>

                <Form.Item
                    label="Email"
                    name="email"
                    rules={[
                        {
                            required: true,
                            message: "Email harus diisi",
                        },
                        {
                            type: "email",
                            message: "Format email tidak valid",
                        },
                    ]}
                >
                    <Input type="email" placeholder="email@example.com" />
                </Form.Item>

                <Form.Item
                    label="No. Telepon"
                    name="phone"
                    rules={[{ required: false }]}
                >
                    <Input placeholder="No. telepon (opsional)" />
                </Form.Item>
            </Form>
        </Modal>
    );
}
