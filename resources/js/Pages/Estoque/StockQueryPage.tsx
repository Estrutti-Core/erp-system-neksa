import React, { useState, useMemo } from 'react';
import { formatCurrency } from '@/data/mockData';
import PageHeader from '@/Components/shared/PageHeader';
import DataTable from '@/Components/shared/DataTable';
import SearchFilter from '@/Components/shared/SearchFilter';
import { ClipboardList } from 'lucide-react';
import MainLayout from '@/Components/layout/MainLayout';
import { Head } from '@inertiajs/react';
import { Badge } from '@/Components/ui/badge';

interface Category {
    id: string;
    name: string;
}

interface Product {
    id: string;
    sku: string | null;
    barcode: string;
    name: string;
    category_id: string;
    category?: Category;
    stock_balance: number;
    min_stock: number;
    unit: string;
    cost_price: number;
    active: boolean;
}

interface Props {
    products: Product[];
    totalValue: number;
}

const StockQueryPage: React.FC<Props> = ({ products, totalValue }) => {
    const [search, setSearch] = useState('');
    const [stockFilter, setStockFilter] = useState('');

    const filteredProducts = useMemo(() => {
        return products.filter(p => {
            const matchesSearch =
                p.name.toLowerCase().includes(search.toLowerCase()) ||
                (p.sku && p.sku.toLowerCase().includes(search.toLowerCase())) ||
                p.barcode.toLowerCase().includes(search.toLowerCase());

            const matchesStock = !stockFilter ||
                (stockFilter === 'low' && p.stock_balance <= p.min_stock && p.stock_balance > 0) ||
                (stockFilter === 'critical' && p.stock_balance <= 0) ||
                (stockFilter === 'ok' && p.stock_balance > p.min_stock);

            return matchesSearch && matchesStock;
        });
    }, [products, search, stockFilter]);

    const getStatusBadge = (p: Product) => {
        if (p.stock_balance <= 0) return <Badge variant="destructive">Zerado</Badge>;
        if (p.stock_balance <= p.min_stock) return <Badge variant="secondary" className="bg-yellow-500 hover:bg-yellow-600 text-white">Baixo</Badge>;
        return <Badge variant="default" className="bg-green-600 hover:bg-green-700">OK</Badge>;
    };

    const columns = [
        { key: 'sku', header: 'SKU', className: 'w-24' },
        { key: 'name', header: 'Produto' },
        {
            key: 'category',
            header: 'Categoria',
            render: (_: unknown, p: Product) => p.category?.name || '-'
        },
        {
            key: 'stock_balance',
            header: 'Estoque',
            render: (_: unknown, p: Product) => (
                <span className={p.stock_balance <= p.min_stock ? 'text-destructive font-medium' : ''}>
                    {p.stock_balance} {p.unit}
                </span>
            )
        },
        {
            key: 'min_stock',
            header: 'Mínimo',
            render: (_: unknown, p: Product) => `${p.min_stock} ${p.unit}`
        },
        {
            key: 'status',
            header: 'Status',
            render: (_: unknown, p: Product) => getStatusBadge(p)
        },
        {
            key: 'value',
            header: 'Valor Estoque',
            render: (_: unknown, p: Product) => formatCurrency(p.stock_balance * p.cost_price)
        },
    ];

    const displayedTotalValue = filteredProducts.reduce((sum, p) => sum + p.stock_balance * p.cost_price, 0);

    return (
        <MainLayout>
            <Head title="Consulta de Estoque" />
            <div>
                <PageHeader
                    title="Consulta de Estoque"
                    subtitle={`Valor total: ${formatCurrency(displayedTotalValue)}`}
                    icon={<ClipboardList className="h-6 w-6" />}
                />

                <SearchFilter
                    searchValue={search}
                    onSearchChange={setSearch}
                    placeholder="Buscar produto..."
                    filters={
                        <select
                            value={stockFilter}
                            onChange={(e) => setStockFilter(e.target.value)}
                            className="w-40 h-10 px-3 py-2 rounded-md border border-input bg-background text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                        >
                            <option value="">Todos</option>
                            <option value="ok">Estoque OK</option>
                            <option value="low">Estoque Baixo</option>
                            <option value="critical">Zerado</option>
                        </select>
                    }
                />

                <DataTable columns={columns} data={filteredProducts} keyField="id" />
            </div>
        </MainLayout>
    );
};

export default StockQueryPage;
