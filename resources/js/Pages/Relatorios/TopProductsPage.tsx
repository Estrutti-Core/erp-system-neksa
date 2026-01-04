import React from 'react';
import PageHeader from '@/Components/shared/PageHeader';
import DataTable from '@/Components/shared/DataTable';
import { Award, Package, DollarSign, TrendingUp } from 'lucide-react';
import { formatCurrency } from '@/data/mockData';
import MainLayout from '@/Components/layout/MainLayout';
import { Head } from '@inertiajs/react';

interface ProductStats {
    id: string;
    name: string;
    code: string;
    quantitySold: number;
    totalRevenue: number;
    avgPrice: number;
}

interface Props {
    productStats: ProductStats[];
    totalRevenue: number;
    totalItems: number;
}

const TopProductsPage: React.FC<Props> = ({ productStats, totalRevenue, totalItems }) => {
    const top10 = productStats.slice(0, 10);
    const maxQty = Math.max(...top10.map(p => p.quantitySold), 1);

    const columns = [
        { key: 'code', header: 'Código', render: (_: unknown, p: ProductStats) => p.code },
        { key: 'name', header: 'Produto', render: (_: unknown, p: ProductStats) => p.name },
        {
            key: 'quantitySold', header: 'Qtd. Vendida', render: (_: unknown, p: ProductStats) => (
                <span className="font-medium">{p.quantitySold}</span>
            )
        },
        { key: 'totalRevenue', header: 'Receita Total', render: (_: unknown, p: ProductStats) => formatCurrency(p.totalRevenue) },
        { key: 'avgPrice', header: 'Preço Médio', render: (_: unknown, p: ProductStats) => formatCurrency(p.avgPrice) },
    ];

    return (
        <MainLayout>
            <Head title="Produtos Mais Vendidos" />
            <div>
                <PageHeader title="Produtos Mais Vendidos" subtitle="Ranking de vendas por produto" icon={<Award className="h-6 w-6" />} />

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div className="bg-card border border-border rounded-lg p-4 flex items-center gap-4">
                        <div className="p-3 bg-primary/10 rounded-lg"><Package className="h-6 w-6 text-primary" /></div>
                        <div>
                            <p className="text-sm text-muted-foreground">Produtos Vendidos</p>
                            <p className="text-2xl font-bold">{productStats.length}</p>
                        </div>
                    </div>
                    <div className="bg-card border border-border rounded-lg p-4 flex items-center gap-4">
                        <div className="p-3 bg-primary/10 rounded-lg"><TrendingUp className="h-6 w-6 text-primary" /></div>
                        <div>
                            <p className="text-sm text-muted-foreground">Total de Itens</p>
                            <p className="text-2xl font-bold">{totalItems}</p>
                        </div>
                    </div>
                    <div className="bg-card border border-border rounded-lg p-4 flex items-center gap-4">
                        <div className="p-3 bg-primary/10 rounded-lg"><DollarSign className="h-6 w-6 text-primary" /></div>
                        <div>
                            <p className="text-sm text-muted-foreground">Receita Total</p>
                            <p className="text-2xl font-bold">{formatCurrency(totalRevenue)}</p>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <div className="lg:col-span-2 bg-card border border-border rounded-lg p-6">
                        <h3 className="text-lg font-semibold mb-4">Top 10 Produtos</h3>
                        <div className="space-y-3">
                            {top10.map((p, i) => (
                                <div key={p.id} className="flex items-center gap-3">
                                    <span className={`w-6 text-center font-bold ${i < 3 ? 'text-primary' : 'text-muted-foreground'}`}>{i + 1}</span>
                                    <div className="flex-1">
                                        <div className="flex justify-between mb-1">
                                            <span className="text-sm font-medium">{p.name}</span>
                                            <span className="text-sm text-muted-foreground">{p.quantitySold} un</span>
                                        </div>
                                        <div className="h-2 bg-muted rounded-full overflow-hidden">
                                            <div className="h-full bg-primary rounded-full" style={{ width: `${(p.quantitySold / maxQty) * 100}%` }} />
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="bg-card border border-border rounded-lg p-6">
                        <h3 className="text-lg font-semibold mb-4">Destaques</h3>
                        {top10.slice(0, 3).map((p, i) => (
                            <div key={p.id} className={`p-3 rounded-lg mb-2 ${i === 0 ? 'bg-yellow-50 dark:bg-yellow-950 border border-yellow-200 dark:border-yellow-800' : 'bg-muted'}`}>
                                <div className="flex items-center gap-2 mb-1">
                                    {i === 0 && <Award className="h-4 w-4 text-yellow-600" />}
                                    <span className="font-medium">{p.name}</span>
                                </div>
                                <p className="text-sm text-muted-foreground">{p.quantitySold} unidades • {formatCurrency(p.totalRevenue)}</p>
                            </div>
                        ))}
                    </div>
                </div>

                <DataTable columns={columns} data={productStats} keyField="id" />
            </div>
        </MainLayout>
    );
};

export default TopProductsPage;
