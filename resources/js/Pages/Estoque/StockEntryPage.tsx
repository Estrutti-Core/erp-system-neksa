import React, { useState } from 'react';
import PageHeader from '@/Components/shared/PageHeader';
import { Package, Save, Plus } from 'lucide-react';
import { formatCurrency, formatDateTime } from '@/data/mockData';
import MainLayout from '@/Components/layout/MainLayout';
import { Head, router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';

interface Product {
    id: string;
    sku: string | null;
    barcode: string;
    name: string;
    stock_balance: number;
    min_stock: number;
    unit: string;
    cost_price: number;
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
    products: Product[];
    recentEntries: StockMovement[];
}

const StockEntryPage: React.FC<Props> = ({ products, recentEntries }) => {
    const [formData, setFormData] = useState({
        product_id: '',
        quantity: '',
        reason: '',
    });
    const [processing, setProcessing] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);

        router.post('/estoque/entrada', {
            product_id: formData.product_id,
            type: 'entry',
            quantity: parseFloat(formData.quantity),
            reason: formData.reason,
        }, {
            onSuccess: () => {
                setFormData({ product_id: '', quantity: '', reason: '' });
                setProcessing(false);
            },
            onError: () => setProcessing(false),
        });
    };

    const selectedProduct = products.find(p => p.id === formData.product_id);

    const reasonOptions = [
        'Compra de fornecedor',
        'Devolução de cliente',
        'Transferência entre lojas',
        'Bonificação',
        'Outro',
    ];

    return (
        <MainLayout>
            <Head title="Entrada de Mercadoria" />
            <div className="space-y-6">
                <PageHeader
                    title="Entrada de Mercadoria"
                    subtitle="Registre a entrada de produtos no estoque"
                    icon={<Package className="h-6 w-6" />}
                />

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Form */}
                    <div className="bg-card text-card-foreground rounded-lg border shadow-sm p-6">
                        <h3 className="text-lg font-semibold flex items-center gap-2 mb-4">
                            <Plus className="h-5 w-5 text-primary" />
                            Nova Entrada
                        </h3>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                                    Produto *
                                </label>
                                <select
                                    value={formData.product_id}
                                    onChange={(e) => setFormData({ ...formData, product_id: e.target.value })}
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 mt-1"
                                    required
                                    disabled={processing}
                                >
                                    <option value="">Selecione um produto</option>
                                    {products.map((product) => (
                                        <option key={product.id} value={product.id}>
                                            {product.sku || product.barcode} - {product.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {selectedProduct && (
                                <div className="bg-muted/50 rounded-md p-3 text-sm space-y-1">
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Estoque atual:</span>
                                        <span className="font-medium">{selectedProduct.stock_balance} {selectedProduct.unit}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Estoque mínimo:</span>
                                        <span className="font-medium">{selectedProduct.min_stock} {selectedProduct.unit}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Preço de custo:</span>
                                        <span className="font-medium">{formatCurrency(selectedProduct.cost_price)}</span>
                                    </div>
                                </div>
                            )}

                            <div>
                                <label className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                                    Quantidade *
                                </label>
                                <input
                                    type="number"
                                    value={formData.quantity}
                                    onChange={(e) => setFormData({ ...formData, quantity: e.target.value })}
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 mt-1"
                                    placeholder="Digite a quantidade"
                                    min="0.01"
                                    step="0.01"
                                    required
                                    disabled={processing}
                                />
                            </div>

                            <div>
                                <label className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                                    Motivo *
                                </label>
                                <select
                                    value={formData.reason}
                                    onChange={(e) => setFormData({ ...formData, reason: e.target.value })}
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 mt-1"
                                    required
                                    disabled={processing}
                                >
                                    <option value="">Selecione o motivo</option>
                                    {reasonOptions.map((reason) => (
                                        <option key={reason} value={reason}>
                                            {reason}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <Button type="submit" className="w-full" disabled={processing}>
                                <Save className="h-4 w-4 mr-2" />
                                {processing ? 'Registrando...' : 'Registrar Entrada'}
                            </Button>
                        </form>
                    </div>

                    {/* Recent entries */}
                    <div className="bg-card text-card-foreground rounded-lg border shadow-sm p-6">
                        <h3 className="text-lg font-semibold mb-4">Últimas Entradas</h3>

                        {recentEntries.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Nenhuma entrada registrada</p>
                        ) : (
                            <div className="space-y-3">
                                {recentEntries.map((entry) => (
                                    <div
                                        key={entry.id}
                                        className="flex items-center justify-between p-3 bg-muted/30 rounded-md"
                                    >
                                        <div>
                                            <p className="font-medium text-sm">{entry.product?.name || 'Produto removido'}</p>
                                            <p className="text-xs text-muted-foreground">{entry.reason}</p>
                                            <p className="text-xs text-muted-foreground">{formatDateTime(entry.created_at)}</p>
                                        </div>
                                        <div className="text-right">
                                            <p className="font-semibold text-primary">+{entry.quantity}</p>
                                            <p className="text-xs text-muted-foreground">{entry.product?.unit || 'UN'}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </MainLayout>
    );
};

export default StockEntryPage;
