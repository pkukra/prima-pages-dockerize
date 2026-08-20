import { Layout, Typography } from "antd";

export default function Guest({ children, showTitle = true }) {
    const currentYear = new Date().getFullYear();

    return (
        <Layout className="guest-layout">
            <Layout.Content>
                <img
                    className="guest-logo"
                    src="/statics/logo.png"
                    alt="PKU Muhammadiyah Karanganyar"
                />
                {showTitle && (
                    <>
                        <Typography.Title level={3} className="guest-title">
                            PRIMA Pages <br /> RS PKU MUHAMMADIYAH KARANGANYAR
                        </Typography.Title>
                    </>
                )}
                <div className="guest-content">{children}</div>
                <Typography.Text type="secondary">
                    © {currentYear} PKU Muhammadiyah Karanganyar
                </Typography.Text>
            </Layout.Content>
        </Layout>
    );
}
