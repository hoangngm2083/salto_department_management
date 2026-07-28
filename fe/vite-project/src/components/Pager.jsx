export default function Pager({ hasPrev, hasNext, onPrev, onNext }) {
  return (
    <div className="mt-4 flex items-center justify-end gap-2">
      <button
        type="button"
        disabled={!hasPrev}
        onClick={onPrev}
        className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 disabled:cursor-not-allowed disabled:opacity-40 hover:bg-gray-100"
      >
        Trước
      </button>
      <button
        type="button"
        disabled={!hasNext}
        onClick={onNext}
        className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 disabled:cursor-not-allowed disabled:opacity-40 hover:bg-gray-100"
      >
        Sau
      </button>
    </div>
  );
}
