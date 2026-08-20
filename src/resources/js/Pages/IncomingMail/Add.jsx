import React, { useEffect, useState } from "react";
import { Head, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import {
    Card,
    Row,
    Col,
    Input,
    DatePicker,
    Button,
    Upload,
    message,
    Select,
} from "antd";
import { UploadOutlined } from "@ant-design/icons";
import axios from "axios";
import dayjs from "dayjs";

const { TextArea } = Input;

export default function Add({ auth }) {
    const [form, setForm] = useState({
        mail_number: "",
        sender: "",
        subject: "",
        incoming_mail_type_id: null,
        mail_date: null,
        received_date: null,
        summary: "",
        file: null,
    });
    const [mailTypes, setMailTypes] = useState([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        axios
            .get(route("incoming.types"))
            .then((resp) => setMailTypes(resp?.data?.data || []))
            .catch((e) => console.error("Error fetching incoming mail types:", e));
    }, []);

    const handleSubmit = async () => {
        if (
            !form.mail_number ||
            !form.sender ||
            !form.subject ||
            !form.incoming_mail_type_id ||
            !form.mail_date ||
            !form.received_date
        ) {
            message.error("Semua field wajib diisi, termasuk Jenis Surat");
            return;
        }

        const formData = new FormData();
        formData.append("mail_number", form.mail_number);
        formData.append("sender", form.sender);
        formData.append("subject", form.subject);
        formData.append("incoming_mail_type_id", form.incoming_mail_type_id);
        formData.append(
            "mail_date",
            dayjs(form.mail_date).format("YYYY-MM-DD"),
        );
        formData.append(
            "received_date",
            dayjs(form.received_date).format("YYYY-MM-DD"),
        );
        formData.append("summary", form.summary || "");
        if (form.file) formData.append("file", form.file);

        setLoading(true);
        try {
            await axios.post(route("incoming.store"), formData, {
                headers: { "Content-Type": "multipart/form-data" },
            });
            message.success("Surat masuk berhasil disimpan");
            setForm({
                mail_number: "",
                sender: "",
                subject: "",
                incoming_mail_type_id: null,
                mail_date: null,
                received_date: null,
                summary: "",
                file: null,
            });
            router.visit(route("incoming.index"));
        } catch (e) {
            if (e?.response?.status === 422) {
                message.error(JSON.stringify(e.response.data.errors));
            } else {
                console.error(e);
                message.error("Terjadi error saat menyimpan");
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<p>Tambah Surat Masuk</p>}
        >
            <Head title="Tambah Surat Masuk" />
            <Card title="Form Surat Masuk">
                <Row gutter={[16, 16]}>
                    <Col span={8}>
                        <Input
                            placeholder="No. Surat"
                            value={form.mail_number}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    mail_number: e.target.value,
                                })
                            }
                        />
                    </Col>
                    <Col span={8}>
                        <Input
                            placeholder="Pengirim"
                            value={form.sender}
                            onChange={(e) =>
                                setForm({ ...form, sender: e.target.value })
                            }
                        />
                    </Col>
                    <Col span={8}>
                        <Select
                            placeholder="Jenis Surat"
                            style={{ width: "100%" }}
                            value={form.incoming_mail_type_id}
                            onChange={(value) =>
                                setForm({
                                    ...form,
                                    incoming_mail_type_id: value || null,
                                })
                            }
                            options={mailTypes.map((type) => ({
                                label: type.name,
                                value: type.id,
                            }))}
                        />
                    </Col>
                    <Col span={24}>
                        <Input
                            placeholder="Perihal"
                            value={form.subject}
                            onChange={(e) =>
                                setForm({ ...form, subject: e.target.value })
                            }
                        />
                    </Col>
                    <Col span={12}>
                        <DatePicker
                            style={{ width: "100%" }}
                            placeholder="Tanggal Surat"
                            value={form.mail_date}
                            onChange={(d) => setForm({ ...form, mail_date: d })}
                        />
                    </Col>
                    <Col span={12}>
                        <DatePicker
                            style={{ width: "100%" }}
                            placeholder="Tanggal Diterima"
                            value={form.received_date}
                            onChange={(d) =>
                                setForm({ ...form, received_date: d })
                            }
                        />
                    </Col>
                    <Col span={24}>
                        <TextArea
                            rows={4}
                            placeholder="Ringkasan (opsional)"
                            value={form.summary}
                            onChange={(e) =>
                                setForm({ ...form, summary: e.target.value })
                            }
                        />
                    </Col>
                    <Col span={24}>
                        <Upload
                            beforeUpload={(file) => {
                                setForm({ ...form, file });
                                return false;
                            }}
                            fileList={form.file ? [form.file] : []}
                        >
                            <Button icon={<UploadOutlined />}>
                                Pilih File
                            </Button>
                        </Upload>
                    </Col>
                    <Col span={24}>
                        <Button
                            type="primary"
                            loading={loading}
                            onClick={handleSubmit}
                        >
                            Simpan
                        </Button>
                    </Col>
                </Row>
            </Card>
        </AuthenticatedLayout>
    );
}
