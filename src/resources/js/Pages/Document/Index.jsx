import React, { useState, useEffect } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import {
    Card,
    Button,
    Table,
    Row,
    Col,
    Input,
    Typography,
} from "antd";
import axios from "axios";

export default function Index({ auth }) {
    const [documents, setDocuments] = useState([]);
    const [fetchDocumentLoading, setFetchDocumentLoading] = useState(false);
    const columnDokumen = [
        {
            title: "Name",
            dataIndex: "name",
            key: "name",
            fixed: "left",
        },
        {
            title: "Description",
            dataIndex: "description",
            key: "description",
            fixed: "left",
        },
        {
            title: "Owner",
            dataIndex: ["owner", "name"],
            key: "owner_id",
            fixed: "left",
        },
        {
            title: "Progress Sign",
            key: "sign_progress",
            render: (_, record) => {
                const signed = Number(record.signed_signers_count || 0);
                const total = Number(record.total_signers || 0);
                return `${signed}/${total}`;
            },
        },
        {
            title: "Issued At",
            dataIndex: "created_at",
            key: "created_at",
            fixed: "left",
        },
        {
            title: "Issued By",
            dataIndex: "created_by",
            key: "created_by",
            fixed: "left",
        },
        {
            title: "Action",
            dataIndex: "action",
            key: "action",
            render: (_, record) => {
                return (
                    <a
                        href={route("docu.viewPage", { id: record.id })}
                    >
                        <Button type="primary" size="small">
                            Tampilkan
                        </Button>
                    </a>
                );
            },
        },
    ];

    const fetcListDocuments = async () => {
        setFetchDocumentLoading(true);
        try {
            const response = await axios.get(route("docu.list_documents"));
            setDocuments(response?.data?.data || []);
        } catch (error) {
            console.error("Error fetcListDocuments: ", error);
        } finally {
            setFetchDocumentLoading(false);
        }
    };

    useEffect(() => {
        fetcListDocuments();
    }, []);

    return (
        <AuthenticatedLayout user={auth.user} header={<p>List Dokumen</p>}>
            <Head title="List Dokument" />
            <Card title="List Dokument" style={{ marginBottom: 5 }}>
                <Row gutter={16} style={{ marginBottom: 10 }}>
                    <Col span={3}>
                        <div>
                            <Typography.Text strong>Judul</Typography.Text>
                        </div>
                        <Input
                            allowClear
                            placeholder="Kode Dokter"
                            value={"kodeDokter"}
                            onChange={(e) => console.log(e.target.value)}
                        />
                    </Col>
                    <Col span={3}>
                        <div>
                            <Typography.Text strong>Penerima</Typography.Text>
                        </div>
                        <Input
                            allowClear
                            placeholder="Penerima"
                            value={"Penerima"}
                            onChange={(e) => console.log(e.target.value)}
                        />
                    </Col>
                    <Col span={3}>
                        <div>
                            <Typography.Text strong>Category</Typography.Text>
                        </div>
                        <Input
                            allowClear
                            placeholder="Category"
                            value={"Category"}
                            onChange={(e) => console.log(e.target.value)}
                        />
                    </Col>
                    <Col span={2}>
                        <div>
                            <Typography.Text>&nbsp;</Typography.Text>
                        </div>
                        <Button block type="primary">
                            Cari
                        </Button>
                    </Col>
                    <Col span={2}>
                        <div>
                            <Typography.Text>&nbsp;</Typography.Text>
                        </div>
                        <Button block>Reset Filter</Button>
                    </Col>
                </Row>
                <Table
                    dataSource={documents}
                    columns={columnDokumen}
                    size="small"
                    loading={fetchDocumentLoading}
                    rowKey="id"
                    pagination={{
                        pageSize: 5,
                    }}
                />
            </Card>
        </AuthenticatedLayout>
    );
}
