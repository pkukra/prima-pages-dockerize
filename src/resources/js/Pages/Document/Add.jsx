import React, { useState, useEffect } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import {
    Card,
    Button,
    Row,
    Col,
    Input,
    Select,
    Upload,
    message,
} from "antd";
import { UploadOutlined } from "@ant-design/icons";
import axios from "axios";

const { TextArea } = Input;

export default function Index({ auth }) {
    const [owners, setOwners] = useState([]);
    const [docTypes, setDocTypes] = useState([]);
    const [signers, setSigners] = useState([]);
    const [form, setForm] = useState({
        name: "",
        description: "",
        owner_id: null,
        type_id: null,
        signer_ids: [],
        file: null,
    });

    const fetcListOwners = async () => {
        try {
            const response = await axios.get(route("docu.list_owners"));
            setOwners(response.data);
        } catch (error) {
            console.error("Error fetcListOwners: ", error);
        } finally {
        }
    };
    
    const fetcListTypes = async () => {
        try {
            const response = await axios.get(route("docu.list_types"));
            setDocTypes(response?.data?.data || []);
        } catch (error) {
            console.error("Error fetcListTypes: ", error);
        } finally {
        }
    };

    const fetchListSigners = async () => {
        try {
            const response = await axios.get(route("docu.list_signers"));
            setSigners(response?.data?.data || []);
        } catch (error) {
            console.error("Error fetchListSigners: ", error);
        } finally {
        }
    };

    const handleSubmit = async () => {
        if (
            !form.name ||
            !form.owner_id ||
            !form.type_id ||
            !form.file ||
            !Array.isArray(form.signer_ids) ||
            form.signer_ids.length === 0
        ) {
            message.error("Nama, owner, tipe, signer, dan file harus diisi!");
            return;
        }

        const formData = new FormData();
        formData.append("name", form.name);
        formData.append("description", form.description);
        formData.append("owner_id", form.owner_id);
        formData.append("type_id", form.type_id);
        formData.append("file", form.file);
        form.signer_ids.forEach((signerId) => {
            formData.append("signer_ids[]", signerId);
        });

        try {
            await axios.post(route("docu.store"), formData, {
                headers: { "Content-Type": "multipart/form-data" },
            });
            message.success("Dokumen berhasil diupload!");
            setForm({
                name: "",
                description: "",
                owner_id: null,
                type_id: null,
                signer_ids: [],
                file: null,
            });
        } catch (error) {
            console.error(error?.response);
            if (
                error?.response?.status === 422 &&
                error?.response?.data?.errors
            ) {
                message.error(JSON.stringify(error.response.data.errors));
                return
            }
        }
    };

    useEffect(() => {
        fetcListOwners();
        fetcListTypes();
        fetchListSigners();
    }, []);

    return (
        <AuthenticatedLayout user={auth.user} header={<p>Upload Document</p>}>
            <Head title="Upload Document" />
            <Card title="Upload Document">
                <Row gutter={[16, 16]}>
                    <Col span={24}>
                        <Input
                            placeholder="Nama Dokumen"
                            value={form.name}
                            onChange={(e) =>
                                setForm({ ...form, name: e.target.value })
                            }
                        />
                    </Col>
                    
                    <Col span={24}>
                        <TextArea
                            rows={4}
                            placeholder="Deskripsi (opsional)"
                            value={form.description}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    description: e.target.value,
                                })
                            }
                        />
                    </Col>
                    <Col span={12}>
                        <Select
                            placeholder="Pilih Owner"
                            value={form.owner_id}
                            style={{ width: "100%" }}
                            onChange={(value) =>
                                setForm({ ...form, owner_id: value })
                            }
                        >
                            {owners.map((owner) => (
                                <Select.Option key={owner.id} value={owner.id}>
                                    {owner.name}
                                </Select.Option>
                            ))}
                        </Select>
                    </Col>
                    <Col span={12}>
                        <Select
                            placeholder="Pilih Tipe"
                            value={form.type_id}
                            style={{ width: "100%" }}
                            onChange={(value) =>
                                setForm({ ...form, type_id: value })
                            }
                        >
                            {docTypes.map((type) => (
                                <Select.Option key={type.id} value={type.id}>
                                    {type.name}
                                </Select.Option>
                            ))}
                        </Select>
                    </Col>
                    <Col span={24}>
                        <Select
                            mode="multiple"
                            placeholder="Pilih Signer Wajib"
                            value={form.signer_ids}
                            style={{ width: "100%" }}
                            optionFilterProp="label"
                            onChange={(value) =>
                                setForm({ ...form, signer_ids: value })
                            }
                            options={signers.map((user) => ({
                                value: user.id,
                                label: `${user.name} (${user.email})`,
                            }))}
                        />
                    </Col>
                    <Col span={24}>
                        <Upload
                            beforeUpload={(file) => {
                                setForm({ ...form, file });
                                return false; // mencegah upload otomatis
                            }}
                            fileList={form.file ? [form.file] : []}
                        >
                            <Button icon={<UploadOutlined />}>
                                Pilih File
                            </Button>
                        </Upload>
                    </Col>
                    <Col span={24}>
                        <Button type="primary" onClick={handleSubmit}>
                            Upload
                        </Button>
                    </Col>
                </Row>
            </Card>
        </AuthenticatedLayout>
    );
}
