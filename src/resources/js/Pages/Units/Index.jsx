import React, { useEffect, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Card, Table, Button, Modal, Form, Input, message, Row, Col } from 'antd';
import axios from 'axios';

export default function Index({ auth }) {
    const [units, setUnits] = useState([]);
    const [loading, setLoading] = useState(false);
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form] = Form.useForm();

    const fetchList = async () => {
        setLoading(true);
        try {
            const resp = await axios.get(route('units.list'));
            setUnits(resp?.data?.data || []);
        } catch (e) {
            console.error(e);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { fetchList(); }, []);

    const openAdd = () => { setEditing(null); form.resetFields(); setModalOpen(true); };
    const openEdit = (record) => { setEditing(record); form.setFieldsValue(record); setModalOpen(true); };

    const handleSubmit = async (vals) => {
        try {
            if (editing) {
                await axios.patch(route('units.update', { id: editing.id }), vals);
                message.success('Unit diperbarui');
            } else {
                await axios.post(route('units.store'), vals);
                message.success('Unit dibuat');
            }
            setModalOpen(false);
            fetchList();
        } catch (e) {
            console.error(e);
            if (e?.response?.data?.errors) message.error(JSON.stringify(e.response.data.errors));
            else message.error('Gagal menyimpan unit');
        }
    };

    const handleDelete = async (id) => {
        if (!confirm('Hapus unit ini?')) return;
        try {
            await axios.delete(route('units.destroy', { id }));
            message.success('Unit dihapus');
            fetchList();
        } catch (e) {
            console.error(e);
            message.error('Gagal menghapus unit');
        }
    };

    const columns = [
        { title: 'Kode', dataIndex: 'code', key: 'code' },
        { title: 'Nama', dataIndex: 'name', key: 'name' },
        { title: 'Deskripsi', dataIndex: 'description', key: 'description' },
        { title: 'Aksi', key: 'action', render: (_, record) => (
            <>
                <Button type="link" onClick={() => openEdit(record)}>Edit</Button>
                <Button type="link" danger onClick={() => handleDelete(record.id)}>Hapus</Button>
            </>
        ) }
    ];

    return (
        <AuthenticatedLayout user={auth.user} header={<p>Master Unit</p>}>
            <Head title="Units" />
            <Card title="Daftar Unit">
                <Row style={{ marginBottom: 12 }}>
                    <Col>
                        <Button type="primary" onClick={openAdd}>Tambah Unit</Button>
                    </Col>
                </Row>
                <Table dataSource={units} columns={columns} rowKey="id" loading={loading} />
            </Card>

            <Modal open={modalOpen} title={editing ? 'Edit Unit' : 'Tambah Unit'} onCancel={() => setModalOpen(false)} footer={null}>
                <Form form={form} layout="vertical" onFinish={handleSubmit}>
                    <Form.Item name="code" label="Kode" rules={[{ required: true }]}><Input /></Form.Item>
                    <Form.Item name="name" label="Nama" rules={[{ required: true }]}><Input /></Form.Item>
                    <Form.Item name="description" label="Deskripsi"><Input.TextArea rows={3} /></Form.Item>
                    <Form.Item style={{ textAlign: 'right' }}>
                        <Button htmlType="submit" type="primary">Simpan</Button>
                    </Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
