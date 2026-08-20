import React, { useState, useEffect } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import { Card, Table, Button, Row, Col, Input, Typography, Select, Space, Tag } from "antd";
import axios from "axios";
import dayjs from "dayjs";
import "dayjs/locale/id";
dayjs.locale("id");

export default function Index({ auth }) {
    const initialQuery = (() => {
        if (typeof window === "undefined") {
            return {
                filterRead: null,
                incomingMailTypeId: null,
                searchKeyword: "",
                page: 1,
                perPage: 8,
            };
        }

        const params = new URLSearchParams(window.location.search);
        const rawPage = Number.parseInt(params.get("page") || "1", 10);
        const rawPerPage = Number.parseInt(params.get("per_page") || "8", 10);
        const rawIncomingMailTypeId = Number.parseInt(params.get("incoming_mail_type_id") || "0", 10);
        const rawFilter = params.get("filter");
        const queryFilter = params.get("queryfilter") || params.get("search") || "";

        return {
            filterRead: ["read", "unread"].includes(rawFilter) ? rawFilter : null,
            incomingMailTypeId: Number.isFinite(rawIncomingMailTypeId) && rawIncomingMailTypeId > 0 ? rawIncomingMailTypeId : null,
            searchKeyword: queryFilter,
            page: Number.isFinite(rawPage) && rawPage > 0 ? rawPage : 1,
            perPage: Number.isFinite(rawPerPage) && rawPerPage > 0 ? rawPerPage : 8,
        };
    })();

    const [mails, setMails] = useState([]);
    const [mailTypes, setMailTypes] = useState([]);
    const [loading, setLoading] = useState(false);
    const [filterRead, setFilterRead] = useState(initialQuery.filterRead); // null, 'read', 'unread'
    const [incomingMailTypeId, setIncomingMailTypeId] = useState(initialQuery.incomingMailTypeId);
    const [searchKeyword, setSearchKeyword] = useState(initialQuery.searchKeyword);
    const [pagination, setPagination] = useState({
        current: initialQuery.page,
        pageSize: initialQuery.perPage,
        total: 0,
    });
    const userRole = auth?.user?.role?.name;

    const columns = [
        {
            title: "No. Surat",
            dataIndex: "mail_number",
            key: "mail_number",
            width: 120,
        },
        { title: "Pengirim", dataIndex: "sender", key: "sender", width: 150 },
        { title: "Perihal", dataIndex: "subject", key: "subject", width: 200 },
        {
            title: "Jenis Surat",
            dataIndex: ["type", "name"],
            key: "incoming_mail_type",
            width: 180,
            render: (v) => v || "-",
        },
        {
            title: "Tgl Surat",
            dataIndex: "mail_date",
            key: "mail_date",
            width: 120,
            render: (v) => (v ? dayjs(v).format("D MMMM YYYY") : "-"),
        },
        {
            title: "Tgl Diterima",
            dataIndex: "received_date",
            key: "received_date",
            width: 120,
            render: (v) => (v ? dayjs(v).format("D MMMM YYYY") : "-"),
        },
        { title: "Dibuat Oleh", dataIndex: "created_by", key: "created_by", width: 120 },
        {
            title: "Status",
            key: "status",
            width: 200,
            render: (_, record) => {
                // Untuk dirut, tampilkan hanya status dibaca
                if (userRole === 'dirut') {
                    return (
                        <Tag color={record.is_read ? "green" : "orange"}>
                            {record.is_read ? "Sudah Dibaca" : "Belum Dibaca"}
                        </Tag>
                    );
                }
                
                // Untuk superadmin, tampilkan semua status
                return (
                    <Space direction="vertical" size="small">
                        {record.is_read ? (
                            <Tag color="green">Sudah Dibaca</Tag>
                        ) : (
                            <Tag color="orange">Belum Dibaca</Tag>
                        )}
                        {record.dirut_read && (
                            <Tag color="blue">✓ Dirut Sudah Baca</Tag>
                        )}
                        {record.all_wadir_read && (
                            <Tag color="cyan">✓ Semua Wakil Direksi Baca</Tag>
                        )}
                    </Space>
                );
            },
        },
        {
            title: "Action",
            key: "action",
            width: 100,
            render: (_, record) => (
                <a href={route("incoming.viewPage", { id: record.id })}>
                    <Button type="link" size="small">Detail</Button>
                </a>
            ),
        },
    ];

    const syncUrlParams = ({ page, perPage, activeFilterRead, activeIncomingMailTypeId, activeSearchKeyword }) => {
        if (typeof window === "undefined") return;

        const url = new URL(window.location.href);
        const params = url.searchParams;
        const keyword = (activeSearchKeyword || "").trim();

        params.set("page", String(page));
        params.set("per_page", String(perPage));

        if (activeFilterRead) {
            params.set("filter", activeFilterRead);
        } else {
            params.delete("filter");
        }

        if (activeIncomingMailTypeId) {
            params.set("incoming_mail_type_id", String(activeIncomingMailTypeId));
        } else {
            params.delete("incoming_mail_type_id");
        }

        if (keyword) {
            params.set("queryfilter", keyword);
        } else {
            params.delete("queryfilter");
        }

        params.delete("search");

        const queryString = params.toString();
        const newUrl = queryString ? `${url.pathname}?${queryString}` : url.pathname;
        window.history.replaceState({}, "", newUrl);
    };

    const fetchList = async (override = {}) => {
        setLoading(true);
        try {
            const effectiveFilterRead = Object.prototype.hasOwnProperty.call(override, "filterRead")
                ? override.filterRead
                : filterRead;
            const effectiveIncomingMailTypeId = Object.prototype.hasOwnProperty.call(override, "incomingMailTypeId")
                ? override.incomingMailTypeId
                : incomingMailTypeId;
            const effectiveSearchKeyword = Object.prototype.hasOwnProperty.call(override, "searchKeyword")
                ? override.searchKeyword
                : searchKeyword;
            const effectivePage = Object.prototype.hasOwnProperty.call(override, "page")
                ? override.page
                : pagination.current;
            const effectivePerPage = Object.prototype.hasOwnProperty.call(override, "perPage")
                ? override.perPage
                : pagination.pageSize;

            const params = {};
            if (effectiveFilterRead) {
                params.filter = effectiveFilterRead;
            }
            if (effectiveIncomingMailTypeId) {
                params.incoming_mail_type_id = effectiveIncomingMailTypeId;
            }
            params.page = effectivePage;
            params.per_page = effectivePerPage;

            const keyword = (effectiveSearchKeyword || "").trim();
            if (keyword) {
                params.queryfilter = keyword;
                params.search = keyword;
            }

            const res = await axios.get(route("incoming.list_incoming_mails"), { params });
            const payload = res?.data?.data || {};
            const list = Array.isArray(payload) ? payload : (payload?.data || []);
            setMails(list);

            if (!Array.isArray(payload)) {
                const currentPage = Number(payload?.current_page || effectivePage);
                const currentPerPage = Number(payload?.per_page || effectivePerPage);

                setPagination({
                    current: currentPage,
                    pageSize: currentPerPage,
                    total: Number(payload?.total || 0),
                });

                syncUrlParams({
                    page: currentPage,
                    perPage: currentPerPage,
                    activeFilterRead: effectiveFilterRead,
                    activeIncomingMailTypeId: effectiveIncomingMailTypeId,
                    activeSearchKeyword: keyword,
                });
            } else {
                setPagination((prev) => ({
                    ...prev,
                    current: 1,
                    total: list.length,
                }));

                syncUrlParams({
                    page: 1,
                    perPage: effectivePerPage,
                    activeFilterRead: effectiveFilterRead,
                    activeIncomingMailTypeId: effectiveIncomingMailTypeId,
                    activeSearchKeyword: keyword,
                });
            }
        } catch (e) {
            console.error(e);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchList();
        axios
            .get(route("incoming.types"))
            .then((resp) => setMailTypes(resp?.data?.data || []))
            .catch((e) => console.error("Error fetching incoming mail types:", e));
    }, []);

    const handleFilterChange = (value) => {
        setFilterRead(value || null);
    };

    const handleTypeFilterChange = (value) => {
        setIncomingMailTypeId(value || null);
    };

    const handleResetFilter = () => {
        setFilterRead(null);
        setIncomingMailTypeId(null);
        setSearchKeyword("");
        fetchList({
            filterRead: null,
            incomingMailTypeId: null,
            searchKeyword: "",
            page: 1,
        });
    };

    const handleTableChange = (tablePagination) => {
        fetchList({
            page: tablePagination.current,
            perPage: tablePagination.pageSize,
        });
    };

    return (
        <AuthenticatedLayout user={auth.user} header={<p>Surat Masuk</p>}>
            <Head title="Surat Masuk" />
            <Card title="List Surat Masuk">
                <Row gutter={16} style={{ marginBottom: 16 }}>
                    {userRole === 'superadmin' && (
                        <Col span={6}>
                            <Typography.Text strong>Filter Status Baca</Typography.Text>
                            <Select
                                placeholder="Pilih filter"
                                allowClear
                                style={{ width: "100%" }}
                                value={filterRead}
                                onChange={handleFilterChange}
                                options={[
                                    { label: "Sudah Dibaca", value: "read" },
                                    { label: "Belum Dibaca", value: "unread" },
                                ]}
                            />
                        </Col>
                    )}
                    <Col span={6}>
                        <Typography.Text strong>Jenis Surat</Typography.Text>
                        <Select
                            placeholder="Semua Jenis Surat"
                            allowClear
                            style={{ width: "100%" }}
                            value={incomingMailTypeId}
                            onChange={handleTypeFilterChange}
                            options={mailTypes.map((type) => ({
                                label: type.name,
                                value: type.id,
                            }))}
                        />
                    </Col>
                    <Col span={6}>
                        <Typography.Text strong>Pencarian</Typography.Text>
                        <Input
                            placeholder="No. Surat / Pengirim / Perihal"
                            value={searchKeyword}
                            onChange={(e) => setSearchKeyword(e.target.value)}
                            onPressEnter={() => fetchList({ page: 1 })}
                        />
                    </Col>
                    <Col span={6} style={{ display: "flex", alignItems: "flex-end", gap: 8, flexWrap: "wrap" }}>
                        <Button type="primary" onClick={() => fetchList({ page: 1 })}>
                            Cari
                        </Button>
                        <Button onClick={handleResetFilter}>
                            Reset
                        </Button>
                        {userRole === 'superadmin' && (
                            <a href={route("incoming.add")}>
                                <Button>Tambah Surat</Button>
                            </a>
                        )}
                    </Col>
                </Row>

                <Table
                    dataSource={mails}
                    columns={columns}
                    rowKey="id"
                    loading={loading}
                    onChange={handleTableChange}
                    pagination={{
                        current: pagination.current,
                        pageSize: pagination.pageSize,
                        total: pagination.total,
                        showSizeChanger: true,
                        pageSizeOptions: [8, 15, 25, 50],
                        showTotal: (total, range) => `${range[0]}-${range[1]} dari ${total}`,
                    }}
                    scroll={{ x: 1200 }}
                />
            </Card>
        </AuthenticatedLayout>
    );
}
