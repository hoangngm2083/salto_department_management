import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { createImport, getImport } from '../api/imports';

const MAX_FILE_SIZE_MB = Number(import.meta.env.VITE_IMPORT_MAX_FILE_SIZE_MB) || 20;
const POLL_INTERVAL_MS = Number(import.meta.env.VITE_IMPORT_POLL_INTERVAL_MS) || 5000;
const MAX_VISIBLE_ERRORS = 50;

const STATUS_LABELS = {
  queued: 'Đang chờ xử lý',
  processing: 'Đang xử lý',
  completed: 'Hoàn tất',
  failed: 'Thất bại',
};

function validateFile(file) {
  if (!file) {
    return 'Vui lòng chọn một file.';
  }

  if (!file.name.toLowerCase().endsWith('.csv')) {
    return 'File phải có định dạng .csv.';
  }

  if (file.size === 0) {
    return 'File không được để trống.';
  }

  if (file.size > MAX_FILE_SIZE_MB * 1024 * 1024) {
    return `File không được vượt quá ${MAX_FILE_SIZE_MB}MB.`;
  }

  return null;
}

function ProgressBar({ percent }) {
  return (
    <div className="h-2 w-full overflow-hidden rounded-full bg-gray-100">
      <div
        className="h-2 rounded-full bg-gray-900 transition-all"
        style={{ width: `${Math.min(100, Math.max(0, percent))}%` }}
      />
    </div>
  );
}

export default function ImportDepartmentsModal({ onClose, onImported }) {
  const [stage, setStage] = useState('idle');
  const [file, setFile] = useState(null);
  const [fileError, setFileError] = useState(null);
  const [submitError, setSubmitError] = useState(null);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [importResult, setImportResult] = useState(null);

  const fileInputRef = useRef(null);
  const pollTimeoutRef = useRef(null);

  useEffect(() => {
    return () => {
      if (pollTimeoutRef.current) {
        clearTimeout(pollTimeoutRef.current);
      }
    };
  }, []);

  function handleFileChange(e) {
    const selected = e.target.files?.[0] ?? null;

    setFile(selected);
    setSubmitError(null);
    setFileError(selected ? validateFile(selected) : null);
  }

  function schedulePoll(importId) {
    pollTimeoutRef.current = setTimeout(async () => {
      try {
        const result = await getImport(importId);
        setImportResult(result);

        if (result.status === 'completed' || result.status === 'failed') {
          setStage('done');

          if (result.status === 'completed') {
            if (result.failed > 0) {
              toast.warning(
                `Nhập xong: ${result.created} tạo mới, ${result.updated} cập nhật, ${result.failed} lỗi.`
              );
            } else {
              toast.success(`Nhập thành công: ${result.created} tạo mới, ${result.updated} cập nhật.`);
            }

            onImported?.();
          } else {
            toast.error('Import thất bại.');
          }

          return;
        }

        schedulePoll(importId);
      } catch {
        // http.js interceptor already shows a toast for the error
        setStage('done');
      }
    }, POLL_INTERVAL_MS);
  }

  async function handleSubmit() {
    const error = validateFile(file);

    if (error) {
      setFileError(error);

      return;
    }

    setStage('uploading');
    setUploadProgress(0);
    setSubmitError(null);

    try {
      const result = await createImport('department', file, {
        onUploadProgress: (evt) => {
          if (evt.total) {
            setUploadProgress(Math.round((evt.loaded * 100) / evt.total));
          }
        },
      });

      setImportResult(result);
      setStage('processing');
      schedulePoll(result.import_id);
    } catch (err) {
      setSubmitError(err.response?.data?.errors?.file?.[0] ?? null);
      setStage('idle');
    }
  }

  function handleReset() {
    setStage('idle');
    setFile(null);
    setFileError(null);
    setSubmitError(null);
    setUploadProgress(0);
    setImportResult(null);

    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  }

  function handleClose() {
    if (pollTimeoutRef.current) {
      clearTimeout(pollTimeoutRef.current);
    }

    onClose();
  }

  const errors = importResult?.errors ?? [];
  const visibleErrors = errors.slice(0, MAX_VISIBLE_ERRORS);

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
      onClick={handleClose}
    >
      <div
        className="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-lg bg-white p-6 shadow-lg"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-lg font-semibold text-gray-900">Nhập phòng ban từ CSV</h2>
          <button
            type="button"
            onClick={handleClose}
            className="text-gray-400 hover:text-gray-600"
            aria-label="Đóng"
          >
            &times;
          </button>
        </div>

        {stage === 'idle' && (
          <>
            <p className="mb-4 text-sm text-gray-500">
              File CSV cần có các cột: <code>name, slug, description, status</code>.
            </p>

            <input
              ref={fileInputRef}
              type="file"
              accept=".csv,text/csv"
              onChange={handleFileChange}
              className="block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border file:border-gray-300 file:bg-white file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-100"
            />

            {fileError && <p className="mt-2 text-sm text-red-600">{fileError}</p>}
            {submitError && <p className="mt-2 text-sm text-red-600">{submitError}</p>}

            <p className="mt-2 text-xs text-gray-400">
              Định dạng .csv, tối đa {MAX_FILE_SIZE_MB}MB.
            </p>

            <div className="mt-6 flex justify-end gap-2">
              <button
                type="button"
                onClick={handleClose}
                className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100"
              >
                Hủy
              </button>
              <button
                type="button"
                onClick={handleSubmit}
                disabled={!file || Boolean(fileError)}
                className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50 hover:bg-gray-700"
              >
                Bắt đầu nhập
              </button>
            </div>
          </>
        )}

        {stage === 'uploading' && (
          <div>
            <p className="mb-2 text-sm text-gray-700">Đang tải lên... {uploadProgress}%</p>
            <ProgressBar percent={uploadProgress} />
          </div>
        )}

        {stage === 'processing' && importResult && (
          <div>
            <p className="mb-2 text-sm text-gray-700">
              Trạng thái: {STATUS_LABELS[importResult.status] ?? importResult.status}
            </p>
            <ProgressBar percent={importResult.progress} />
            <p className="mt-2 text-xs text-gray-500">
              {importResult.processed}/{importResult.total} dòng đã xử lý
            </p>
            <p className="mt-4 text-xs text-gray-400">
              Quá trình nhập vẫn tiếp tục chạy ở máy chủ dù bạn đóng cửa sổ này.
            </p>

            <div className="mt-6 flex justify-end">
              <button
                type="button"
                onClick={handleClose}
                className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100"
              >
                Đóng
              </button>
            </div>
          </div>
        )}

        {stage === 'done' && importResult && (
          <div>
            {importResult.status === 'failed' && (
              <p className="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                Import thất bại.
              </p>
            )}

            <div className="grid grid-cols-3 gap-3 text-center">
              <div className="rounded-md border border-gray-200 p-3">
                <p className="text-lg font-semibold text-gray-900">{importResult.created}</p>
                <p className="text-xs text-gray-500">Tạo mới</p>
              </div>
              <div className="rounded-md border border-gray-200 p-3">
                <p className="text-lg font-semibold text-gray-900">{importResult.updated}</p>
                <p className="text-xs text-gray-500">Cập nhật</p>
              </div>
              <div className="rounded-md border border-gray-200 p-3">
                <p className="text-lg font-semibold text-gray-900">{importResult.failed}</p>
                <p className="text-xs text-gray-500">Lỗi</p>
              </div>
            </div>

            {errors.length > 0 && (
              <div className="mt-4">
                <p className="mb-2 text-sm font-medium text-gray-700">Danh sách lỗi</p>
                <div className="max-h-48 overflow-y-auto rounded-md border border-gray-200">
                  <ul className="divide-y divide-gray-100 text-sm">
                    {visibleErrors.map((e, i) => (
                      <li key={i} className="px-3 py-1.5 text-gray-600">
                        Dòng {e.row}: {e.message}
                      </li>
                    ))}
                  </ul>
                </div>
                {errors.length > MAX_VISIBLE_ERRORS && (
                  <p className="mt-1 text-xs text-gray-400">
                    +{errors.length - MAX_VISIBLE_ERRORS} lỗi khác
                  </p>
                )}
              </div>
            )}

            <div className="mt-6 flex justify-end gap-2">
              <button
                type="button"
                onClick={handleReset}
                className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100"
              >
                Nhập file khác
              </button>
              <button
                type="button"
                onClick={handleClose}
                className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
              >
                Đóng
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
