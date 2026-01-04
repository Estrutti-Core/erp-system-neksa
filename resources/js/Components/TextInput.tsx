import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';

export default forwardRef(function TextInput(
    { type = 'text', className = '', isFocused = false, ...props },
    ref,
) {
    const localRef = useRef(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) {
            localRef.current?.focus();
        }
    }, [isFocused]);

    return (
        <input
            {...props}
            type={type}
            className={
                'w-full rounded-xl border-white/10 bg-white/5 px-4 py-3 text-white placeholder-slate-500 shadow-sm transition-all focus:border-blue-500 focus:bg-white/10 focus:ring-blue-500 ' +
                className
            }
            ref={localRef}
        />
    );
});
