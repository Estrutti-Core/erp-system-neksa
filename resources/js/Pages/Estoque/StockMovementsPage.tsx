import React, { useState, useMemo } from 'react';
import PageHeader from '@/Components/shared/PageHeader';
import DataTable from '@/Components/shared/DataTable';
import SearchFilter from '@/Components/shared/SearchFilter';
import { ArrowLeftRight } from 'lucide-react';
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
    unit: string;
    category?: Category;
}

interface User {
    id: number;
    name: string;
}

interface StockMovement {
    id: string;
    product_id: string;
    type: string;
    quantity: number;
    reason: string;
    created_at: string;
    product?: Product;
    user?: User;
}

interface Props {
    movements: {
        data: StockMovement[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    statistics: {
        total_entries: number;
        total_exits: number;
        total_adjustments: number;
    };
}

const StockMovementsPage: React.FC<Props> = ({ movements, statistics }) => {
    const [search, setSearch] = useState('');
    const [typeFilter, setTypeFilter] = useState('');

    const filteredMovements = useMemo(() => {
        return movements.data.filter(m => {
            const matchesSearch = !search ||
                m.product?.name.toLowerCase().includes(search.toLowerCase()) ||
                (m.product?.sku && m.product.sku.toLowerCase().includes(search.toLowerCase())) ||
                m.product?.barcode.toLowerCase().includes(search.toLowerCase());
            const matchesType = !typeFilter || m.type === typeFilter;
            return matchesSearch && matchesType;
        });
    }, [movements.data, search, typeFilter]);

    const getTypeLabel = (type: string) => {
        switch (type) {
            case 'entry': return 'Entrada';
            case 'exit': return 'Saída';
            case 'adjustment': return 'Ajuste';
            case 'sale': return 'Venda';
            case 'return': return 'Devolução';
            default: return type;
        }
    };

    const getStatusVariant = (type: string): "default" | "secondary" | "destructive" | "outline" => {
        switch (type) {
            case 'entry': return 'default';
            case 'exit': return 'destructive';
            case 'sale': return 'destructive';
            case 'adjustment': return 'secondary';
            case 'return': return 'default';
            default: return 'outline';
        }
    };

    const columns = [
        {
            key: 'date',
            header: 'Data',
            render: (_: unknown, m: StockMovement) => new Date(m.created_at).toLocaleDateString('pt-BR')
        },
        {
            key: 'product_code',
            header: 'Código',
            render: (_: unknown, m: StockMovement) => m.product?.sku || m.product?.barcode || '-'
        },
        {
            key: 'product',
            header: 'Produto',
            render: (_: unknown, m: StockMovement) => m.product?.name || 'Produto removido'
        },
        {
            key: 'type',
            header: 'Tipo',
            render: (_: unknown, m: StockMovement) => (
                <Badge variant={getStatusVariant(m.type)}>{getTypeLabel(m.type)}</Badge>
            )
        },
        {
            key: 'quantity',
            header: 'Quantidade',
            render: (_: unknown, m: StockMovement) => (
                <span className={
                    m.type === 'entry' || m.type === 'return' ? 'text-green-600 font-medium' :
                        m.type === 'exit' || m.type === 'sale' ? 'text-destructive font-medium' :
                            'font-medium'
                }>
                    {m.type === 'entry' || m.type === 'return' ? '+' : m.type === 'exit' || m.type === 'sale' ? '-' : ''}{m.quantity}
                </span>
            )
        },
        { key: 'reason', header: 'Motivo' },
        {
            key: 'user',
            header: 'Usuário',
            render: (_: unknown, m: StockMovement) => m.user?.name || '-'
        },
    ];

    return (
        <MainLayout>
            <Head title="Movimentações de Estoque" />
            <div>
                <PageHeader
                    title="Movimentações de Estoque"
                    subtitle={`Entradas: ${statistics.total_entries} | Saídas: ${statistics.total_exits} | Ajustes: ${statistics.total_adjustments}`}
                    icon={<ArrowLeftRight className="h-6 w-6" />}
                />

                <SearchFilter
                    searchValue={search}
                    onSearchChange={setSearch}
                    placeholder="Buscar produto..."
                    filters={
                        <select
                            value={typeFilter}
                            onChange={e => setTypeFilter(e.target.value)}
                            className="w-40 h-10 px-3 py-2 rounded-md border border-input bg-background text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                        >
                            <option value="">Todos</option>
                            <option value="entry">Entradas</option>
                            <option value="exit">Saídas</option>
                            <option value="adjustment">Ajustes</option>
                            <option value="sale">Vendas</option>
                            <option value="return">Devoluções</option>
                        </select>
                    }
                />

                <DataTable columns={columns} data={filteredMovements} keyField="id" />

                {movements.total > movements.per_page && (
                    <div className="mt-4 text-sm text-muted-foreground text-center">
                        Mostrando {filteredMovements.length} de {movements.total} movimentações
                    </div>
                )}
            </div>
        </MainLayout>
    );
};

export default StockMovementsPage;
