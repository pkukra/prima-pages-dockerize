import React, { useState, useEffect } from "react";
import { usePage } from "@inertiajs/react";
import {
    FileProtectOutlined,
    MailOutlined,
    UserOutlined,
    HomeOutlined,
    PoweroffOutlined,
    IdcardOutlined,
} from "@ant-design/icons";
import { Layout, Menu } from "antd";

const { Sider, Content } = Layout;

const App = ({ children }) => {
    const [collapsed, setCollapsed] = useState(false);
    const { url, auth } = usePage();

    useEffect(() => {
        const savedCollapsed = localStorage.getItem("collapsed");
        if (savedCollapsed !== null) {
            setCollapsed(JSON.parse(savedCollapsed));
        }
    }, []);

    const handleCollapseChange = (value) => {
        setCollapsed(value);
        localStorage.setItem("collapsed", JSON.stringify(value));
    };

    const currentKey = url.split("/")[3] || "";
    const userRole = auth?.user?.role?.name;

    // Build menu items dinamis berdasarkan role
    const items = [
        {
            key: "",
            icon: <HomeOutlined />,
            label: <a href={route("dashboard")}>Home</a>,
        },
        {
            key: "docu",
            icon: <FileProtectOutlined />,
            label: <a href={route("docu.index")}>Pages</a>,
            children: [
                {
                    key: "docu-list",
                    label: (
                        <a href={route("docu.index")}>
                            Pages List
                        </a>
                    ),
                },
                ...(userRole === "admin" || userRole === "superadmin"
                    ? [
                          {
                              key: "docu-add",
                              label: (
                                  <a href={route("docu.add")}>
                                      Tambah Page
                                  </a>
                              ),
                          },
                      ]
                    : []),
            ],
        },
    ];

    // Menu Surat Masuk - ditampilkan untuk semua role
    items.push({
        key: "incoming",
        icon: <MailOutlined />,
        label: <a>Surat Masuk</a>,
        children: [
            {
                key: "incoming-list",
                label: <a href={route("incoming.index")}>List Surat</a>,
            },
            {
                key: "incoming-add",
                label: <a href={route("incoming.add")}>Tambah Surat</a>,
            },
            {
                key: "dispositions-list",
                label: <a href={route("dispositions.my")}>Disposisi Ke Saya</a>,
            },
            {
                key: "dispositions-list-created",
                label: <a href={route("dispositions.created")}>Disposisi Dibuat</a>,
            }
        ],
    });

    // Menu yang ditampilkan semua role
    items.push(
        {
            key: "tilaka",
            icon: <IdcardOutlined />,
            label: <a href={route("tilaka.index")}>Data Tilaka</a>,
        },
        {
            key: "profile",
            icon: <UserOutlined />,
            label: <a href={route("profile.edit")}>Profile</a>,
        },
        {
            key: "logout",
            icon: <PoweroffOutlined />,
            label: <a href={route("logout")}>Logout</a>,
        }
    );

    return (
        <Layout style={{ minHeight: "100vh" }}>
            <Sider
                collapsible
                collapsed={collapsed}
                onCollapse={handleCollapseChange}
            >
                <div className="demo-logo-vertical" />
                <Menu
                    theme="dark"
                    mode="inline"
                    selectedKeys={[currentKey]}
                    items={items}
                />
            </Sider>
            <Layout>
                <Content style={{ margin: "16px" }}>{children}</Content>
            </Layout>
        </Layout>
    );
};

export default App;
