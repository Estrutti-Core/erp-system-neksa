import React, { useMemo } from 'react';
import { useERP } from '@/contexts/ERPContext';
import PageHeader from '@/Components/shared/PageHeader';
import { Wallet, TrendingUp, TrendingDown, DollarSign } from 'lucide-react';
import { formatCurrency } from '@/data/mockData';
import MainLayout from '@/Components/layout/MainLayout';
import { Head } from '@inertiajs/react';

const CashFlowPage: React.FC = () => {
    const { sales, accountsPayable, accountsReceivable } = useERP();

    const stats = useMemo(() => {
        const totalVendas = sales.reduce((sum, s) => sum + s.total, 0);
        const totalRecebido = accountsReceivable.filter(a => a.status === 'paid').reduce((sum, a) => sum + a.amount, 0);
        const totalPago = accountsPayable.filter(a => a.status === 'paid').reduce((sum, a) => sum + a.amount, 0);
        const aPagar = accountsPayable.filter(a => a.status === 'pending').reduce((sum, a) => sum + a.amount, 0);
        const aReceber = accountsReceivable.filter(a => a.status === 'pending').reduce((sum, a) => sum + a.amount, 0);
        const saldo = totalRecebido - totalPago;

        return { totalVendas, totalRecebido, totalPago, aPagar, aReceber, saldo };
    }, [sales, accountsPayable, accountsReceivable]);

    const monthlyData = [
        { month: 'Jan', entradas: 45000, saidas: 32000 },
        { month: 'Fev', entradas: 52000, saidas: 38000 },
        { month: 'Mar', entradas: 48000, saidas: 35000 },
        { month: 'Abr', entradas: 61000, saidas: 42000 },
        { month: 'Mai', entradas: 55000, saidas: 39000 },
        { month: 'Jun', entradas: 67000, saidas: 45000 },
    ];

    return (
        <MainLayout>
            <Head title="Fluxo de Caixa" />
            <div>
                <PageHeader title="Fluxo de Caixa" subtitle="Visão geral das finanças" icon={<Wallet className="h-6 w-6" />} />

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div className="bg-card border border-border rounded-lg p-4">
                        <div className="flex items-center gap-3">
                            <div className="p-2 bg-primary/10 rounded-lg"><DollarSign className="h-5 w-5 text-primary" /></div>
                            <div>
                                <p className="text-sm text-muted-foreground">Saldo Atual</p>
                                <p className={`text-xl font-bold ${stats.saldo >= 0 ? 'text-green-600' : 'text-destructive'}`}>{formatCurrency(stats.saldo)}</p>
                            </div>
                        </div>
                    </div>
                    <div className="bg-card border border-border rounded-lg p-4">
                        <div className="flex items-center gap-3">
                            <div className="p-2 bg-green-100 dark:bg-green-900 rounded-lg"><TrendingUp className="h-5 w-5 text-green-600" /></div>
                            <div>
                                <p className="text-sm text-muted-foreground">Total Recebido</p>
                                <p className="text-xl font-bold text-green-600">{formatCurrency(stats.totalRecebido)}</p>
                            </div>
                        </div>
                    </div>
                    <div className="bg-card border border-border rounded-lg p-4">
                        <div className="flex items-center gap-3">
                            <div className="p-2 bg-red-100 dark:bg-red-900 rounded-lg"><TrendingDown className="h-5 w-5 text-destructive" /></div>
                            <div>
                                <p className="text-sm text-muted-foreground">Total Pago</p>
                                <p className="text-xl font-bold text-destructive">{formatCurrency(stats.totalPago)}</p>
                            </div>
                        </div>
                    </div>
                    <div className="bg-card border border-border rounded-lg p-4">
                        <div className="flex items-center gap-3">
                            <div className="p-2 bg-primary/10 rounded-lg"><Wallet className="h-5 w-5 text-primary" /></div>
                            <div>
                                <p className="text-sm text-muted-foreground">Vendas</p>
                                <p className="text-xl font-bold">{formatCurrency(stats.totalVendas)}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div className="bg-card border border-border rounded-lg p-6">
                        <h3 className="text-lg font-semibold mb-4">Resumo Mensal</h3>
                        <div className="space-y-3">
                            {monthlyData.map(m => (
                                <div key={m.month} className="flex items-center gap-4">
                                    <span className="w-10 text-sm font-medium">{m.month}</span>
                                    <div className="flex-1">
                                        <div className="flex gap-2 mb-1">
                                            <div className="h-4 bg-green-500 rounded" style={{ width: `${(m.entradas / 70000) * 100}%` }} />
                                        </div>
                                        <div className="flex gap-2">
                                            <div className="h-4 bg-destructive rounded" style={{ width: `${(m.saidas / 70000) * 100}%` }} />
                                        </div>
                                    </div>
                                    <div className="text-right text-sm">
                                        <p className="text-green-600">+{formatCurrency(m.entradas)}</p>
                                        <p className="text-destructive">-{formatCurrency(m.saidas)}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                        <div className="flex gap-4 mt-4 text-sm">
                            <span className="flex items-center gap-1"><span className="w-3 h-3 bg-green-500 rounded" /> Entradas</span>
                            <span className="flex items-center gap-1"><span className="w-3 h-3 bg-destructive rounded" /> Saídas</span>
                        </div>
                    </div>

                    <div className="bg-card border border-border rounded-lg p-6">
                        <h3 className="text-lg font-semibold mb-4">Pendências</h3>
                        <div className="space-y-4">
                            <div className="p-4 bg-green-50 dark:bg-green-950 border border-green-200 dark:border-green-800 rounded-lg">
                                <p className="text-sm text-green-700 dark:text-green-300">A Receber</p>
                                <p className="text-2xl font-bold text-green-600">{formatCurrency(stats.aReceber)}</p>
                                <p className="text-xs text-green-600">{accountsReceivable.filter(a => a.status === 'pending').length} títulos pendentes</p>
                            </div>
                            <div className="p-4 bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 rounded-lg">
                                <p className="text-sm text-red-700 dark:text-red-300">A Pagar</p>
                                <p className="text-2xl font-bold text-destructive">{formatCurrency(stats.aPagar)}</p>
                                <p className="text-xs text-destructive">{accountsPayable.filter(a => a.status === 'pending').length} títulos pendentes</p>
                            </div>
                            <div className="p-4 bg-muted rounded-lg">
                                <p className="text-sm text-muted-foreground">Saldo Projetado</p>
                                <p className="text-2xl font-bold">{formatCurrency(stats.saldo + stats.aReceber - stats.aPagar)}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
};

export default CashFlowPage;
