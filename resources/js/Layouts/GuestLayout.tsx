import { Link } from '@inertiajs/react';
import { Store } from 'lucide-react';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen items-center justify-center bg-[#0f172a] selection:bg-primary selection:text-white">
            {/* Background elements for premium feel */}
            <div className="fixed inset-0 overflow-hidden pointer-events-none">
                <div className="absolute -top-[10%] -left-[10%] w-[40%] h-[40%] rounded-full bg-blue-500/10 blur-[120px]" />
                <div className="absolute -bottom-[10%] -right-[10%] w-[40%] h-[40%] rounded-full bg-indigo-500/10 blur-[120px]" />
            </div>

            <div className="relative w-full max-w-[440px] px-6 py-12">
                <div className="mb-10 flex flex-col items-center">
                    <Link href="/" className="group flex flex-col items-center gap-4 transition-transform hover:scale-105">
                        <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 text-white shadow-[0_8px_30px_rgb(37,99,235,0.4)] transition-all group-hover:shadow-[0_8px_40px_rgb(37,99,235,0.6)]">
                            <Store className="h-9 w-9" />
                        </div>
                        <div className="text-center">
                            <h1 className="text-3xl font-extrabold tracking-tight text-white">
                                Mercado <span className="text-blue-500">ERP</span>
                            </h1>
                            <p className="mt-1 text-sm font-medium text-slate-400">
                                Gestão inteligente para o seu negócio
                            </p>
                        </div>
                    </Link>
                </div>

                <div className="overflow-hidden rounded-3xl border border-white/10 bg-white/5 p-8 shadow-2xl backdrop-blur-xl sm:p-10">
                    {children}
                </div>

                <div className="mt-10 text-center">
                    <p className="text-xs font-medium text-slate-500">
                        &copy; {new Date().getFullYear()} Mercado System. Todos os direitos reservados.
                    </p>
                </div>
            </div>
        </div>
    );
}
