import React, { useState, useMemo } from 'react';
import { Category } from '@/data/mockData';
import PageHeader from '@/Components/shared/PageHeader';
import DataTable from '@/Components/shared/DataTable';
import Modal from '@/Components/shared/Modal';
import SearchFilter from '@/Components/shared/SearchFilter';
import { FormField, Input, Textarea, Checkbox } from '@/Components/shared/FormComponents';
import { Tags, Plus, Edit, Trash2 } from 'lucide-react';
import MainLayout from '@/Components/layout/MainLayout';
import { Head, router } from '@inertiajs/react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';

interface Props {
    categories: (Category & { products_count?: number })[];
}

const CategoriesPage: React.FC<Props> = ({ categories }) => {
    const [search, setSearch] = useState('');
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingCategory, setEditingCategory] = useState<Category | null>(null);
    const [formData, setFormData] = useState({
        name: '',
        description: '',
        active: true,
    });
    const [processing, setProcessing] = useState(false);

    const filteredCategories = useMemo(() => {
        return categories.filter(c =>
            c.name.toLowerCase().includes(search.toLowerCase()) ||
            (c.description && c.description.toLowerCase().includes(search.toLowerCase()))
        );
    }, [categories, search]);

    const openModal = (category?: Category) => {
        if (category) {
            setEditingCategory(category);
            setFormData({
                name: category.name,
                description: category.description || '',
                active: category.active,
            });
        } else {
            setEditingCategory(null);
            setFormData({
                name: '',
                description: '',
                active: true,
            });
        }
        setIsModalOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);

        if (editingCategory) {
            router.put(`/cadastros/categorias/${editingCategory.id}`, formData, {
                onSuccess: () => {
                    setIsModalOpen(false);
                    setProcessing(false);
                },
                onError: () => setProcessing(false),
            });
        } else {
            router.post('/cadastros/categorias', formData, {
                onSuccess: () => {
                    setIsModalOpen(false);
                    setProcessing(false);
                },
                onError: () => setProcessing(false),
            });
        }
    };

    const handleDelete = (category: Category & { products_count?: number }) => {
        if (category.products_count && category.products_count > 0) {
            alert(`Não é possível excluir. Existem ${category.products_count} produtos nesta categoria.`);
            return;
        }
        if (window.confirm(`Deseja excluir a categoria "${category.name}"?`)) {
            router.delete(`/cadastros/categorias/${category.id}`);
        }
    };

    const columns = [
        { key: 'name', header: 'Nome' },
        { key: 'description', header: 'Descrição' },
        {
            key: 'products',
            header: 'Produtos',
            render: (_: unknown, c: Category & { products_count?: number }) => c.products_count || 0,
        },
        {
            key: 'active',
            header: 'Status',
            render: (_: unknown, c: Category) => (
                <Badge variant={c.active ? 'default' : 'secondary'}>{c.active ? 'Ativo' : 'Inativo'}</Badge>
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-24',
            render: (_: unknown, c: Category & { products_count?: number }) => (
                <div className="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={(e) => { e.stopPropagation(); openModal(c); }}
                    >
                        <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="text-destructive"
                        onClick={(e) => { e.stopPropagation(); handleDelete(c); }}
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <MainLayout>
            <Head title="Categorias" />
            <div>
                <PageHeader
                    title="Categorias"
                    subtitle={`${categories.length} categorias cadastradas`}
                    icon={<Tags className="h-6 w-6" />}
                    actions={
                        <Button onClick={() => openModal()}>
                            <Plus className="h-4 w-4 mr-2" />
                            Nova Categoria
                        </Button>
                    }
                />

                <SearchFilter
                    searchValue={search}
                    onSearchChange={setSearch}
                    placeholder="Buscar categoria..."
                />

                <DataTable
                    columns={columns}
                    data={filteredCategories}
                    keyField="id"
                    onRowClick={openModal}
                />

                <Modal
                    isOpen={isModalOpen}
                    onClose={() => setIsModalOpen(false)}
                    title={editingCategory ? 'Editar Categoria' : 'Nova Categoria'}
                >
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Nome" required>
                            <Input
                                value={formData.name}
                                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                placeholder="Nome da categoria"
                                required
                                disabled={processing}
                            />
                        </FormField>

                        <FormField label="Descrição">
                            <Textarea
                                value={formData.description}
                                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                                placeholder="Descrição da categoria"
                                disabled={processing}
                            />
                        </FormField>

                        <Checkbox
                            label="Categoria ativa"
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
                                {processing ? 'Salvando...' : (editingCategory ? 'Salvar' : 'Cadastrar')}
                            </Button>
                        </div>
                    </form>
                </Modal>
            </div>
        </MainLayout>
    );
};

export default CategoriesPage;
