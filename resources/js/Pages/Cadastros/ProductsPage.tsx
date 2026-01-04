import React, { useState, useMemo } from 'react';
import { formatCurrency, Product } from '@/data/mockData';
import PageHeader from '@/Components/shared/PageHeader';
import DataTable from '@/Components/shared/DataTable';
import Modal from '@/Components/shared/Modal';
import SearchFilter from '@/Components/shared/SearchFilter';
import { FormField, Input, Select, Checkbox } from '@/Components/shared/FormComponents';
import { Package, Plus, Edit, Trash2 } from 'lucide-react';
import MainLayout from '@/Components/layout/MainLayout';
import { Head, router } from '@inertiajs/react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';

interface Category {
    id: string;
    name: string;
    active: boolean;
}

interface Supplier {
    id: string;
    name: string;
    active: boolean;
}

interface Props {
    products: (Product & { category?: Category; supplier?: Supplier })[];
    categories: Category[];
    suppliers: Supplier[];
}

const ProductsPage: React.FC<Props> = ({ products, categories, suppliers }) => {
    const [search, setSearch] = useState('');
    const [categoryFilter, setCategoryFilter] = useState('');
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingProduct, setEditingProduct] = useState<Product | null>(null);
    const [formData, setFormData] = useState({
        sku: '',
        barcode: '',
        name: '',
        category_id: '',
        supplier_id: '',
        cost_price: '',
        sale_price: '',
        stock_balance: '',
        min_stock: '',
        unit: 'UN',
        active: true,
    });
    const [processing, setProcessing] = useState(false);

    const filteredProducts = useMemo(() => {
        return products.filter(p => {
            const matchesSearch =
                p.name.toLowerCase().includes(search.toLowerCase()) ||
                (p.sku && p.sku.toLowerCase().includes(search.toLowerCase())) ||
                (p.barcode && p.barcode.toLowerCase().includes(search.toLowerCase()));
            const matchesCategory = !categoryFilter || p.category_id === categoryFilter;
            return matchesSearch && matchesCategory;
        });
    }, [products, search, categoryFilter]);

    const getCategoryName = (id: string) => {
        const product = products.find(p => p.category_id === id);
        return product?.category?.name || '-';
    };

    const openModal = (product?: Product) => {
        if (product) {
            setEditingProduct(product);
            setFormData({
                sku: product.sku || '',
                barcode: product.barcode || '',
                name: product.name,
                category_id: product.category_id,
                supplier_id: product.supplier_id || '',
                cost_price: product.cost_price?.toString() || '',
                sale_price: product.sale_price?.toString() || '',
                stock_balance: product.stock_balance?.toString() || '0',
                min_stock: product.min_stock?.toString() || '10',
                unit: product.unit || 'UN',
                active: product.active,
            });
        } else {
            setEditingProduct(null);
            setFormData({
                sku: '',
                barcode: '',
                name: '',
                category_id: categories[0]?.id || '',
                supplier_id: suppliers[0]?.id || '',
                cost_price: '',
                sale_price: '',
                stock_balance: '0',
                min_stock: '10',
                unit: 'UN',
                active: true,
            });
        }
        setIsModalOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);

        if (editingProduct) {
            router.put(`/cadastros/produtos/${editingProduct.id}`, formData, {
                onSuccess: () => {
                    setIsModalOpen(false);
                    setProcessing(false);
                },
                onError: () => setProcessing(false),
            });
        } else {
            router.post('/cadastros/produtos', formData, {
                onSuccess: () => {
                    setIsModalOpen(false);
                    setProcessing(false);
                },
                onError: () => setProcessing(false),
            });
        }
    };

    const handleDelete = (product: Product) => {
        if (window.confirm(`Deseja excluir o produto "${product.name}"?`)) {
            router.delete(`/cadastros/produtos/${product.id}`);
        }
    };

    const columns = [
        { key: 'sku', header: 'SKU', className: 'w-24' },
        { key: 'name', header: 'Nome' },
        {
            key: 'category_id',
            header: 'Categoria',
            render: (_: unknown, p: Product & { category?: Category }) => p.category?.name || '-',
        },
        {
            key: 'stock_balance',
            header: 'Estoque',
            render: (_: unknown, p: Product) => (
                <span className={p.stock_balance && p.min_stock && p.stock_balance <= p.min_stock ? 'text-destructive font-medium' : ''}>
                    {p.stock_balance || 0} {p.unit}
                </span>
            ),
        },
        {
            key: 'sale_price',
            header: 'Preço Venda',
            render: (_: unknown, p: Product) => formatCurrency(p.sale_price || 0),
        },
        {
            key: 'active',
            header: 'Status',
            render: (_: unknown, p: Product) => (
                <Badge variant={p.active ? 'default' : 'secondary'}>{p.active ? 'Ativo' : 'Inativo'}</Badge>
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-24',
            render: (_: unknown, p: Product) => (
                <div className="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={(e) => { e.stopPropagation(); openModal(p); }}
                        title="Editar"
                    >
                        <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="text-destructive"
                        onClick={(e) => { e.stopPropagation(); handleDelete(p); }}
                        title="Excluir"
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <MainLayout>
            <Head title="Produtos" />
            <div>
                <PageHeader
                    title="Produtos"
                    subtitle={`${products.length} produtos cadastrados`}
                    icon={<Package className="h-6 w-6" />}
                    actions={
                        <Button onClick={() => openModal()}>
                            <Plus className="h-4 w-4 mr-1" />
                            Novo Produto
                        </Button>
                    }
                />

                <SearchFilter
                    searchValue={search}
                    onSearchChange={setSearch}
                    placeholder="Buscar por nome ou código..."
                    filters={
                        <select
                            value={categoryFilter}
                            onChange={(e) => setCategoryFilter(e.target.value)}
                            className="w-[200px] h-10 px-3 py-2 rounded-md border border-input bg-background text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                        >
                            <option value="">Todas as categorias</option>
                            {categories.map(c => (
                                <option key={c.id} value={c.id}>{c.name}</option>
                            ))}
                        </select>
                    }
                />

                <DataTable
                    columns={columns}
                    data={filteredProducts}
                    keyField="id"
                    onRowClick={openModal}
                />

                <Modal
                    isOpen={isModalOpen}
                    onClose={() => setIsModalOpen(false)}
                    title={editingProduct ? 'Editar Produto' : 'Novo Produto'}
                    size="lg"
                >
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="SKU">
                                <Input
                                    value={formData.sku}
                                    onChange={(e) => setFormData({ ...formData, sku: e.target.value })}
                                    placeholder="Ex: PROD001"
                                    disabled={processing}
                                />
                            </FormField>
                            <FormField label="Código de Barras" required>
                                <Input
                                    value={formData.barcode}
                                    onChange={(e) => setFormData({ ...formData, barcode: e.target.value })}
                                    placeholder="Ex: 7891234567890"
                                    required
                                    disabled={processing}
                                />
                            </FormField>
                        </div>

                        <FormField label="Nome" required>
                            <Input
                                value={formData.name}
                                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                placeholder="Nome do produto"
                                required
                                disabled={processing}
                            />
                        </FormField>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Categoria" required>
                                <Select
                                    value={formData.category_id}
                                    onChange={(e) => setFormData({ ...formData, category_id: e.target.value })}
                                    required
                                    disabled={processing}
                                >
                                    <option value="">Selecione...</option>
                                    {categories.filter(c => c.active).map(c => (
                                        <option key={c.id} value={c.id}>{c.name}</option>
                                    ))}
                                </Select>
                            </FormField>
                            <FormField label="Fornecedor">
                                <Select
                                    value={formData.supplier_id}
                                    onChange={(e) => setFormData({ ...formData, supplier_id: e.target.value })}
                                    disabled={processing}
                                >
                                    <option value="">Selecione...</option>
                                    {suppliers.filter(s => s.active).map(s => (
                                        <option key={s.id} value={s.id}>{s.name}</option>
                                    ))}
                                </Select>
                            </FormField>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Preço de Custo" required>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={formData.cost_price}
                                    onChange={(e) => setFormData({ ...formData, cost_price: e.target.value })}
                                    placeholder="0,00"
                                    required
                                    disabled={processing}
                                />
                            </FormField>
                            <FormField label="Preço de Venda" required>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={formData.sale_price}
                                    onChange={(e) => setFormData({ ...formData, sale_price: e.target.value })}
                                    placeholder="0,00"
                                    required
                                    disabled={processing}
                                />
                            </FormField>
                        </div>

                        <div className="grid grid-cols-3 gap-4">
                            <FormField label="Estoque Inicial">
                                <Input
                                    type="number"
                                    min="0"
                                    value={formData.stock_balance}
                                    onChange={(e) => setFormData({ ...formData, stock_balance: e.target.value })}
                                    disabled={!!editingProduct || processing}
                                />
                            </FormField>
                            <FormField label="Estoque Mínimo">
                                <Input
                                    type="number"
                                    min="0"
                                    value={formData.min_stock}
                                    onChange={(e) => setFormData({ ...formData, min_stock: e.target.value })}
                                    disabled={processing}
                                />
                            </FormField>
                            <FormField label="Unidade">
                                <Select
                                    value={formData.unit}
                                    onChange={(e) => setFormData({ ...formData, unit: e.target.value })}
                                    disabled={processing}
                                >
                                    <option value="UN">Unidade (UN)</option>
                                    <option value="KG">Quilograma (KG)</option>
                                    <option value="L">Litro (L)</option>
                                    <option value="M">Metro (M)</option>
                                    <option value="CX">Caixa (CX)</option>
                                </Select>
                            </FormField>
                        </div>

                        <Checkbox
                            label="Produto ativo"
                            checked={formData.active}
                            onChange={(e) => setFormData({ ...formData, active: e.target.checked })}
                            disabled={processing}
                        />

                        <div className="flex justify-end gap-3 pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsModalOpen(false)}
                                disabled={processing}
                            >
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Salvando...' : (editingProduct ? 'Salvar' : 'Cadastrar')}
                            </Button>
                        </div>
                    </form>
                </Modal>
            </div>
        </MainLayout>
    );
};

export default ProductsPage;
