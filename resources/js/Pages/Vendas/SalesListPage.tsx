import React, { useState, useMemo } from 'react';
import { formatCurrency, formatDateTime } from '@/data/mockData';
import PageHeader from '@/Components/shared/PageHeader';
import DataTable from '@/Components/shared/DataTable';
import Modal from '@/Components/shared/Modal';
import SearchFilter from '@/Components/shared/SearchFilter';
import { ShoppingCart, Eye } from 'lucide-react';
import MainLayout from '@/Components/layout/MainLayout';
import { Head } from '@inertiajs/react';
import { Badge } from '@/Components/ui/badge';

interface Product {
    id: string;
    name: string;
}

interface SaleItem {
    id: string;
    product_id: string;
    quantity: number;
    unit_price: number;
    total: number;
    product?: Product;
}

interface Customer {
    id: string;
    name: string;
}

interface User {
    id: number;
    name: string;
}

interface PaymentMethod {
    id: string;
    name: string;
}

interface Sale {
    id: string;
    sale_number: number;
    created_at: string;
    customer_id?: string;
    user_id: number;
    payment_method_id: string;
    subtotal: number;
    discount: number;
    total: number;
    status: string;
    items: SaleItem[];
    customer?: Customer;
    user?: User;
    paymentMethod?: PaymentMethod;
}

interface Props {
    sales: Sale[];
    paymentMethods: PaymentMethod[];
}

const SalesListPage: React.FC<Props> = ({ sales, paymentMethods }) => {
    const [search, setSearch] = useState('');
    const [paymentFilter, setPaymentFilter] = useState('');
    const [selectedSale, setSelectedSale] = useState<Sale | null>(null);

    const filteredSales = useMemo(() => {
        return sales.filter(s => {
            const customerName = s.customer?.name || 'Consumidor';
            const matchesSearch = customerName.toLowerCase().includes(search.toLowerCase()) || s.id.includes(search);
            const matchesPayment = !paymentFilter || s.payment_method_id === paymentFilter;
            return matchesSearch && matchesPayment;
        });
    }, [sales, search, paymentFilter]);

    const getStatusBadge = (status: string) => {
        const variants: Record<string, "default" | "secondary" | "destructive" | "outline"> = {
            completed: 'default',
            cancelled: 'destructive',
            pending: 'secondary'
        };
        const labels: Record<string, string> = {
            completed: 'Concluída',
            cancelled: 'Cancelada',
            pending: 'Pendente'
        };
        return <Badge variant={variants[status] || 'outline'}>{labels[status] || status}</Badge>;
    };

    const columns = [
        { key: 'id', header: 'ID', className: 'w-24', render: (_: unknown, s: Sale) => `#${String(s.sale_number).padStart(6, '0')}` },
        { key: 'date', header: 'Data', render: (_: unknown, s: Sale) => formatDateTime(s.created_at) },
        { key: 'customer', header: 'Cliente', render: (_: unknown, s: Sale) => s.customer?.name || 'Consumidor' },
        { key: 'payment', header: 'Pagamento', render: (_: unknown, s: Sale) => s.paymentMethod?.name || '-' },
        { key: 'items', header: 'Itens', render: (_: unknown, s: Sale) => s.items.length },
        { key: 'total', header: 'Total', render: (_: unknown, s: Sale) => formatCurrency(s.total) },
        { key: 'status', header: 'Status', render: (_: unknown, s: Sale) => getStatusBadge(s.status) },
        {
            key: 'actions', header: '', className: 'w-16', render: (_: unknown, s: Sale) => (
                <button onClick={(e) => { e.stopPropagation(); setSelectedSale(s); }} className="p-2 hover:bg-muted rounded-md transition-colors"><Eye className="h-4 w-4" /></button>
            )
        },
    ];

    return (
        <MainLayout>
            <Head title="Lista de Vendas" />
            <div>
                <PageHeader title="Lista de Vendas" subtitle={`${sales.length} vendas registradas`} icon={<ShoppingCart className="h-6 w-6" />} />
                <SearchFilter searchValue={search} onSearchChange={setSearch} placeholder="Buscar por cliente..."
                    filters={
                        <select value={paymentFilter} onChange={(e) => setPaymentFilter(e.target.value)} className="w-[200px] h-10 px-3 py-2 rounded-md border border-input bg-background text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2">
                            <option value="">Todos os pagamentos</option>
                            {paymentMethods.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                        </select>
                    }
                />
                <DataTable columns={columns} data={filteredSales} keyField="id" onRowClick={setSelectedSale} />

                <Modal isOpen={!!selectedSale} onClose={() => setSelectedSale(null)} title={`Venda #${String(selectedSale?.sale_number).padStart(6, '0')}`} size="lg">
                    {selectedSale && (
                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-4 text-sm">
                                <div><span className="text-muted-foreground font-medium">Cliente:</span> {selectedSale.customer?.name || 'Consumidor'}</div>
                                <div><span className="text-muted-foreground font-medium">Data:</span> {formatDateTime(selectedSale.created_at)}</div>
                                <div><span className="text-muted-foreground font-medium">Vendedor:</span> {selectedSale.user?.name || '-'}</div>
                                <div><span className="text-muted-foreground font-medium">Pagamento:</span> {selectedSale.paymentMethod?.name || '-'}</div>
                            </div>
                            <div className="border rounded-lg overflow-hidden">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted">
                                        <tr>
                                            <th className="px-4 py-2 text-left">Produto</th>
                                            <th className="px-4 py-2 text-left">Qtd</th>
                                            <th className="px-4 py-2 text-left">Unitário</th>
                                            <th className="px-4 py-2 text-left">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {selectedSale.items.map((item) => (
                                            <tr key={item.id}>
                                                <td className="px-4 py-2">{item.product?.name || 'Produto removido'}</td>
                                                <td className="px-4 py-2">{item.quantity}</td>
                                                <td className="px-4 py-2">{formatCurrency(item.unit_price)}</td>
                                                <td className="px-4 py-2">{formatCurrency(item.total)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <div className="flex flex-col items-end gap-1 text-sm border-t border-border pt-4">
                                <div className="text-muted-foreground">Subtotal: {formatCurrency(selectedSale.subtotal)}</div>
                                <div className="text-muted-foreground">Desconto: {formatCurrency(selectedSale.discount)}</div>
                                <div className="text-lg font-bold">Total: {formatCurrency(selectedSale.total)}</div>
                            </div>
                        </div>
                    )}
                </Modal>
            </div>
        </MainLayout>
    );
};

export default SalesListPage;
