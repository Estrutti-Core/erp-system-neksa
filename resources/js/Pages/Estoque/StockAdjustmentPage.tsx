import React, { useState } from 'react';
import PageHeader from '@/Components/shared/PageHeader';
import { Settings, Plus, Minus } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import MainLayout from '@/Components/layout/MainLayout';
import { Head, router } from '@inertiajs/react';

interface Product {
    id: string;
    sku: string | null;
    barcode: string;
    name: string;
    stock_balance: number;
    min_stock: number;
    unit: string;
}

interface Props {
    products: Product[];
    criticalProducts: Product[];
}

const StockAdjustmentPage: React.FC<Props> = ({ products, criticalProducts }) => {
    const [selectedProduct, setSelectedProduct] = useState('');
    const [adjustmentType, setAdjustmentType] = useState<'add' | 'remove'>('add');
    const [quantity, setQuantity] = useState('');
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);

    const product = products.find(p => p.id === selectedProduct);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);

        router.post('/estoque/ajuste', {
            product_id: selectedProduct,
            type: 'adjustment',
            quantity: parseFloat(quantity),
            reason: reason,
            adjustment_type: adjustmentType,
        }, {
            onSuccess: () => {
                setSelectedProduct('');
                setQuantity('');
                setReason('');
                setProcessing(false);
            },
            onError: () => setProcessing(false),
        });
    };

    return (
        <MainLayout>
            <Head title="Ajuste de Estoque" />
            <div>
                <PageHeader
                    title="Ajuste de Estoque"
                    subtitle="Correção manual de quantidades"
                    icon={<Settings className="h-6 w-6" />}
                />

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div className="bg-card border border-border rounded-lg p-6">
                        <h3 className="text-lg font-semibold mb-4">Novo Ajuste</h3>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium mb-1">Produto</label>
                                <select
                                    value={selectedProduct}
                                    onChange={e => setSelectedProduct(e.target.value)}
                                    className="w-full h-10 px-3 py-2 rounded-md border border-input bg-background text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                    required
                                    disabled={processing}
                                >
                                    <option value="">Selecione...</option>
                                    {products.map(p => (
                                        <option key={p.id} value={p.id}>
                                            {p.sku || p.barcode} - {p.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {product && (
                                <div className="p-3 bg-muted rounded-lg text-sm">
                                    <p>Estoque atual: <strong>{product.stock_balance} {product.unit}</strong></p>
                                    <p>Estoque mínimo: {product.min_stock} {product.unit}</p>
                                </div>
                            )}

                            <div>
                                <label className="block text-sm font-medium mb-1">Tipo de Ajuste</label>
                                <div className="flex gap-2">
                                    <Button
                                        type="button"
                                        variant={adjustmentType === 'add' ? 'default' : 'outline'}
                                        onClick={() => setAdjustmentType('add')}
                                        className="flex-1"
                                        disabled={processing}
                                    >
                                        <Plus className="h-4 w-4 mr-1" /> Adicionar
                                    </Button>
                                    <Button
                                        type="button"
                                        variant={adjustmentType === 'remove' ? 'destructive' : 'outline'}
                                        onClick={() => setAdjustmentType('remove')}
                                        className="flex-1"
                                        disabled={processing}
                                    >
                                        <Minus className="h-4 w-4 mr-1" /> Remover
                                    </Button>
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-medium mb-1">Quantidade</label>
                                <input
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    value={quantity}
                                    onChange={e => setQuantity(e.target.value)}
                                    className="w-full h-10 px-3 py-2 rounded-md border border-input bg-background text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                    required
                                    disabled={processing}
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium mb-1">Motivo</label>
                                <select
                                    value={reason}
                                    onChange={e => setReason(e.target.value)}
                                    className="w-full h-10 px-3 py-2 rounded-md border border-input bg-background text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                    required
                                    disabled={processing}
                                >
                                    <option value="">Selecione...</option>
                                    <option value="Inventário">Inventário</option>
                                    <option value="Perda/Avaria">Perda/Avaria</option>
                                    <option value="Correção de erro">Correção de erro</option>
                                    <option value="Devolução">Devolução</option>
                                    <option value="Outro">Outro</option>
                                </select>
                            </div>

                            <Button type="submit" className="w-full" disabled={processing}>
                                {processing ? 'Processando...' : 'Confirmar Ajuste'}
                            </Button>
                        </form>
                    </div>

                    <div className="bg-card border border-border rounded-lg p-6">
                        <h3 className="text-lg font-semibold mb-4">Produtos com Estoque Crítico</h3>
                        <div className="space-y-2">
                            {criticalProducts.length === 0 ? (
                                <p className="text-sm text-muted-foreground">Nenhum produto com estoque crítico</p>
                            ) : (
                                criticalProducts.map(p => (
                                    <div key={p.id} className="flex justify-between items-center p-3 bg-muted rounded-lg">
                                        <div>
                                            <p className="font-medium">{p.name}</p>
                                            <p className="text-sm text-muted-foreground">{p.sku || p.barcode}</p>
                                        </div>
                                        <div className="text-right">
                                            <p className={p.stock_balance === 0 ? 'text-destructive font-bold' : 'text-yellow-600 font-medium'}>
                                                {p.stock_balance} {p.unit}
                                            </p>
                                            <p className="text-xs text-muted-foreground">Mín: {p.min_stock}</p>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
};

export default StockAdjustmentPage;
