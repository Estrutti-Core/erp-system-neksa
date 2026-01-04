import React from 'react';
import { formatCurrency } from '@/data/mockData';
import PageHeader from '@/Components/shared/PageHeader';
import { AlertTriangle } from 'lucide-react';
import MainLayout from '@/Components/layout/MainLayout';
import { Head } from '@inertiajs/react';

interface Category {
    id: string;
    name: string;
}

interface Supplier {
    id: string;
    name: string;
}

interface Product {
    id: string;
    sku: string | null;
    barcode: string;
    name: string;
    stock_balance: number;
    min_stock: number;
    unit: string;
    category?: Category;
    supplier?: Supplier;
}

interface Props {
    criticalProducts: Product[];
}

const CriticalStockPage: React.FC<Props> = ({ criticalProducts }) => {
    return (
        <MainLayout>
            <Head title="Estoque Crítico" />
            <div>
                <PageHeader
                    title="Estoque Crítico"
                    subtitle={`${criticalProducts.length} produtos precisam de reposição`}
                    icon={<AlertTriangle className="h-6 w-6 text-destructive" />}
                />

                <div className="bg-card text-card-foreground rounded-lg border shadow-sm overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 border-b">
                            <tr>
                                <th className="h-10 px-4 text-left font-medium">Código</th>
                                <th className="h-10 px-4 text-left font-medium">Produto</th>
                                <th className="h-10 px-4 text-left font-medium">Categoria</th>
                                <th className="h-10 px-4 text-left font-medium">Estoque</th>
                                <th className="h-10 px-4 text-left font-medium">Mínimo</th>
                                <th className="h-10 px-4 text-left font-medium">Falta</th>
                                <th className="h-10 px-4 text-left font-medium">Fornecedor</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {criticalProducts.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="py-8 text-center text-muted-foreground italic">
                                        Nenhum produto com estoque crítico
                                    </td>
                                </tr>
                            ) : criticalProducts.map(p => (
                                <tr key={p.id} className="hover:bg-muted/30 transition-colors">
                                    <td className="px-4 py-3">{p.sku || p.barcode}</td>
                                    <td className="px-4 py-3 font-medium">{p.name}</td>
                                    <td className="px-4 py-3 text-muted-foreground">{p.category?.name || '-'}</td>
                                    <td className={`px-4 py-3 ${p.stock_balance === 0 ? 'text-destructive font-bold' : 'text-orange-600 font-medium'}`}>
                                        {p.stock_balance} {p.unit}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">{p.min_stock} {p.unit}</td>
                                    <td className="px-4 py-3 font-semibold text-destructive">
                                        {Math.max(0, p.min_stock - p.stock_balance)} {p.unit}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">{p.supplier?.name || '-'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </MainLayout>
    );
};

export default CriticalStockPage;
