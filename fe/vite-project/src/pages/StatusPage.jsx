import { Link } from 'react-router-dom';

export default function StatusPage({ code, title, message }) {
  return (
    <div className="flex flex-col items-center justify-center py-24 text-center">
      <p className="text-4xl font-semibold text-gray-300">{code}</p>
      <h1 className="mt-2 text-xl font-semibold text-gray-900">{title}</h1>
      <p className="mt-2 text-sm text-gray-500">{message}</p>
      <Link
        to="/"
        className="mt-6 rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
      >
        Về trang chủ
      </Link>
    </div>
  );
}
