import React, { useState } from 'react';
import { useERP } from '@/contexts/ERPContext';
import PageHeader from '@/Components/shared/PageHeader';
import DataTable from '@/Components/shared/DataTable';
import { Wallet, Filter, CheckCircle, AlertCircle, Clock } from 'lucide-react';
import { formatCurrency, formatDate } from '@/data/mockData';
import { toast } from 'sonner';
import MainLayout from '@/Components/layout/MainLayout';
import { Head } from '@inertiajs/react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';

type StatusFilter = 'all' | 'pending' | 'paid' | 'overdue';

const AccountsReceivablePage: React.FC = () => {
    const { accountsReceivable, updateAccountReceivable } = useERP();
    const [statusFilter, setStatusFilter] = useState<StatusFilter>('all');

    const filteredAccounts = accountsReceivable.filter((account) => {
        if (statusFilter === 'all') return true;
        return account.status === statusFilter;
    });

    const handleMarkAsPaid = (id: string) => {
        updateAccountReceivable(id, {
            status: 'paid',
            paidDate: new Date().toISOString().split('T')[0],
        });
        toast.success('Conta marcada como recebida!');
    };

    const totals = {
        pending: accountsReceivable
            .filter((a) => a.status === 'pending')
            .reduce((sum, a) => sum + a.amount, 0),
        overdue: accountsReceivable
            .filter((a) => a.status === 'overdue')
            .reduce((sum, a) => sum + a.amount, 0),
        paid: accountsReceivable
            .filter((a) => a.status === 'paid')
            .reduce((sum, a) => sum + a.amount, 0),
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'pending': return <Badge variant="outline" className="bg-blue-100 text-blue-800 border-blue-200">A Receber</Badge>;
            case 'paid': return <Badge variant="outline" className="bg-green-100 text-green-800 border-green-200">Recebido</Badge>;
            case 'overdue': return <Badge variant="destructive">Vencido</Badge>;
            default: return <Badge variant="secondary">{status}</Badge>;
        }
    };

    const columns = [
        {
            key: 'customerName',
            header: 'Cliente',
            render: (_: unknown, a: typeof accountsReceivable[0]) => <span className="font-medium">{a.customerName}</span>,
        },
        {
            key: 'description',
            header: 'Descrição',
            render: (_: unknown, a: typeof accountsReceivable[0]) => a.description,
        },
        {
            key: 'amount',
            header: 'Valor',
            render: (_: unknown, a: typeof accountsReceivable[0]) => (
                <span className="font-semibold">{formatCurrency(a.amount)}</span>
            ),
        },
        {
            key: 'dueDate',
            header: 'Vencimento',
            render: (_: unknown, a: typeof accountsReceivable[0]) => formatDate(a.dueDate),
        },
        {
            key: 'paidDate',
            header: 'Data Recebimento',
            render: (_: unknown, a: typeof accountsReceivable[0]) => (a.paidDate ? formatDate(a.paidDate) : '-'),
        },
        {
            key: 'status',
            header: 'Status',
            render: (_: unknown, a: typeof accountsReceivable[0]) => getStatusBadge(a.status),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-36',
            render: (_: unknown, item: typeof accountsReceivable[0]) => (
                <div className="flex gap-2">
                    {item.status !== 'paid' && (
                        <Button
                            onClick={() => handleMarkAsPaid(item.id)}
                            size="sm"
                            className="bg-green-600 hover:bg-green-700 text-white text-xs h-8"
                        >
                            <CheckCircle className="h-3 w-3 mr-1" />
                            Marcar Recebido
                        </Button>
                    )}
                </div>
            ),
        }
    ];

    return (
        <MainLayout>
            <Head title="Contas a Receber" />
            <div className="space-y-6">
                <PageHeader
                    title="Contas a Receber"
                    subtitle="Gerencie os recebimentos do estabelecimento"
                    icon={<Wallet className="h-6 w-6" />}
                />

                {/* Summary Cards */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="bg-card text-card-foreground rounded-lg border shadow-sm p-4">
                        <div className="flex items-center gap-3">
                            <div className="p-2 bg-blue-100 rounded-md">
                                <Clock className="h-5 w-5 text-blue-600" />
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">A Receber</p>
                                <p className="text-lg font-semibold">{formatCurrency(totals.pending)}</p>
                            </div>
                        </div>
                    </div>
                    <div className="bg-card text-card-foreground rounded-lg border shadow-sm p-4">
                        <div className="flex items-center gap-3">
                            <div className="p-2 bg-destructive/10 rounded-md">
                                <AlertCircle className="h-5 w-5 text-destructive" />
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Vencidas</p>
                                <p className="text-lg font-semibold text-destructive">{formatCurrency(totals.overdue)}</p>
                            </div>
                        </div>
                    </div>
                    <div className="bg-card text-card-foreground rounded-lg border shadow-sm p-4">
                        <div className="flex items-center gap-3">
                            <div className="p-2 bg-green-100 rounded-md">
                                <CheckCircle className="h-5 w-5 text-green-600" />
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Recebidas</p>
                                <p className="text-lg font-semibold text-green-600">{formatCurrency(totals.paid)}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Filters */}
                <div className="bg-card text-card-foreground rounded-lg border shadow-sm p-4">
                    <div className="flex flex-col sm:flex-row sm:items-center gap-4">
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <Filter className="h-4 w-4" />
                            <span className="text-sm font-medium">Filtrar por status:</span>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {[
                                { value: 'all', label: 'Todos' },
                                { value: 'pending', label: 'A Receber' },
                                { value: 'overdue', label: 'Vencidas' },
                                { value: 'paid', label: 'Recebidas' },
                            ].map((option) => (
                                <Button
                                    key={option.value}
                                    variant={statusFilter === option.value ? "default" : "secondary"}
                                    size="sm"
                                    onClick={() => setStatusFilter(option.value as StatusFilter)}
                                    className="text-xs"
                                >
                                    {option.label}
                                </Button>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Table */}
                <DataTable
                    data={filteredAccounts}
                    columns={columns}
                    keyField="id"
                />
            </div>
        </MainLayout>
    );
};

export default AccountsReceivablePage;
