import React, { useState } from "react";
import { Modal, Upload, Button, message } from "antd";
import { InboxOutlined } from "@ant-design/icons";

export default function UploadDocumentModal({
    open,
    onClose,
    onUpload,
    documentType = "ktp",
    loading = false,
}) {
    const [fileList, setFileList] = useState([]);

    const handleCancel = () => {
        setFileList([]);
        onClose();
    };

    const handleUpload = async () => {
        if (fileList.length === 0) {
            message.error("Silakan pilih file terlebih dahulu");
            return;
        }

        const file = fileList[0];

        // Validate file type
        const allowedTypes = [
            "image/jpeg",
            "image/jpg",
            "image/png",
            "application/pdf",
        ];
        if (!allowedTypes.includes(file.type)) {
            message.error(
                "Tipe file tidak didukung. Gunakan JPG, PNG, atau PDF",
            );
            return;
        }

        // Validate file size (max 5MB)
        const maxSize = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSize) {
            message.error("Ukuran file tidak boleh lebih dari 5MB");
            return;
        }

        onUpload(file);
        setFileList([]);
    };

    const handleBeforeUpload = (file) => {
        // Just add to fileList, actual upload handled in handleUpload
        setFileList([file]);
        return false;
    };

    const handleRemove = () => {
        setFileList([]);
    };

    const documentLabel =
        documentType === "ktp"
            ? "Foto KTP"
            : documentType === "selfie"
              ? "Foto Selfie"
              : "File Tanda Tangan";
    const title = `Upload ${documentLabel}`;

    return (
        <Modal
            title={title}
            open={open}
            onCancel={handleCancel}
            width={500}
            footer={[
                <Button
                    key="cancel"
                    onClick={handleCancel}
                    disabled={loading}
                >
                    Batal
                </Button>,
                <Button
                    key="submit"
                    type="primary"
                    loading={loading}
                    onClick={handleUpload}
                    disabled={fileList.length === 0}
                >
                    Upload
                </Button>,
            ]}
        >
            <div style={{ marginBottom: 16 }}>
                <p>
                    Pilih file untuk di-upload. Format yang didukung: JPG, PNG,
                    PDF. Ukuran maksimal: 5MB.
                </p>
            </div>

            <Upload
                maxCount={1}
                fileList={fileList}
                beforeUpload={handleBeforeUpload}
                onRemove={handleRemove}
                accept=".jpg,.jpeg,.png,.pdf"
            >
                <Button block>
                    Pilih File
                </Button>
            </Upload>
        </Modal>
    );
}
