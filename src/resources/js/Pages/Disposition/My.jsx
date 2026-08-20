import React from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import { Card, Table, Tag } from "antd";
import dayjs from "dayjs";

export default function My({ auth, dispositions = [] }) {
    const columns = [
        {
            title: "No. Surat",
            dataIndex: ["mail", "mail_number"],
            key: "mail_number",
            render: (_, record) => (
                <a href={route('incoming.viewPage', { id: record.incoming_mail_id })}>
                    {record.mail?.mail_number}
                </a>
            ),
        },
        { title: "Perihal", dataIndex: ["mail", "subject"], key: "subject" },
        { title: "Instruksi", dataIndex: "instruction", key: "instruction" },
        { title: "Untuk Unit", dataIndex: ["unit", "name"], key: "unit" },
        {
            title: "Tanggal Disposisi",
            dataIndex: "created_at",
            key: "created_at",
            render: (v) => (v ? dayjs(v).format("D MMMM YYYY") : "-"),
            sorter: (a, b) => {
                const da = a.created_at ? new Date(a.created_at).getTime() : 0;
                const db = b.created_at ? new Date(b.created_at).getTime() : 0;
                return da - db;
            },
        },
        {
            title: "Status Resolve",
            dataIndex: "status",
            key: "status",
            render: (v) =>
                v === "resolved" ? (
                    <Tag color="green">Resolved</Tag>
                ) : (
                    <Tag color="orange">Belum</Tag>
                ),
            sorter: (a, b) => {
                const sa = a.status === "resolved" ? 1 : 0;
                const sb = b.status === "resolved" ? 1 : 0;
                return sa - sb;
            },
        },
        {
            title: "Status Dibuka",
            dataIndex: "is_unit_read",
            key: "is_unit_read",
            render: (v) =>
                Number(v) === 1 ? (
                    <Tag color="green">Sudah Dibuka/Diunduh</Tag>
                ) : (
                    <Tag color="orange">Belum Dibuka</Tag>
                ),
            sorter: (a, b) =>
                Number(a.is_unit_read || 0) - Number(b.is_unit_read || 0),
        },
    ];

    return (
        <AuthenticatedLayout user={auth.user} header={<p>Disposisi Saya</p>}>
            <Head title="Disposisi Saya" />
            <Card title="Disposisi untuk Unit Saya">
                <Table
                    dataSource={dispositions}
                    columns={columns}
                    rowKey={(record) => record.id}
                    pagination={{ pageSize: 10 }}
                />
            </Card>
        </AuthenticatedLayout>
    );
}
