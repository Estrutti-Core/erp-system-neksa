export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                `inline-flex w-full items-center justify-center rounded-xl border border-transparent bg-blue-600 px-6 py-3 text-sm font-bold text-white shadow-lg transition-all duration-150 ease-in-out hover:bg-blue-700 hover:shadow-blue-500/25 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-[#0f172a] active:scale-95 disabled:hover:bg-blue-600 ${disabled && 'opacity-50 cursor-not-allowed'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
