import React, { useState, useMemo } from 'react';
import { Supplier } from '@/data/mockData';
import PageHeader from '@/Components/shared/PageHeader';
import DataTable from '@/Components/shared/DataTable';
import Modal from '@/Components/shared/Modal';
import SearchFilter from '@/Components/shared/SearchFilter';
import { FormField, Input, Textarea, Checkbox } from '@/Components/shared/FormComponents';
import { Truck, Plus, Edit, Trash2 } from 'lucide-react';
import MainLayout from '@/Components/layout/MainLayout';
import { Head, router } from '@inertiajs/react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';

interface Props {
    suppliers: Supplier[];
}

const SuppliersPage: React.FC<Props> = ({ suppliers }) => {
    const [search, setSearch] = useState('');
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingSupplier, setEditingSupplier] = useState<Supplier | null>(null);
    const [formData, setFormData] = useState({
        name: '',
        cnpj: '',
        email: '',
        phone: '',
        address: '',
        active: true,
    });
    const [processing, setProcessing] = useState(false);

    const filteredSuppliers = useMemo(() => {
        return suppliers.filter(s =>
            s.name.toLowerCase().includes(search.toLowerCase()) ||
            (s.cnpj && s.cnpj.includes(search))
        );
    }, [suppliers, search]);

    const openModal = (supplier?: Supplier) => {
        if (supplier) {
            setEditingSupplier(supplier);
            setFormData({
                name: supplier.name,
                cnpj: supplier.cnpj || '',
                email: supplier.email || '',
                phone: supplier.phone || '',
                address: supplier.address || '',
                active: supplier.active,
            });
        } else {
            setEditingSupplier(null);
            setFormData({
                name: '',
                cnpj: '',
                email: '',
                phone: '',
                address: '',
                active: true,
            });
        }
        setIsModalOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);

        if (editingSupplier) {
            router.put(`/cadastros/fornecedores/${editingSupplier.id}`, formData, {
                onSuccess: () => {
                    setIsModalOpen(false);
                    setProcessing(false);
                },
                onError: () => setProcessing(false),
            });
        } else {
            router.post('/cadastros/fornecedores', formData, {
                onSuccess: () => {
                    setIsModalOpen(false);
                    setProcessing(false);
                },
                onError: () => setProcessing(false),
            });
        }
    };

    const handleDelete = (supplier: Supplier) => {
        if (window.confirm(`Deseja excluir o fornecedor "${supplier.name}"?`)) {
            router.delete(`/cadastros/fornecedores/${supplier.id}`);
        }
    };

    const columns = [
        { key: 'name', header: 'Nome' },
        { key: 'cnpj', header: 'CNPJ' },
        { key: 'phone', header: 'Telefone' },
        { key: 'email', header: 'E-mail' },
        {
            key: 'active',
            header: 'Status',
            render: (_: unknown, s: Supplier) => (
                <Badge variant={s.active ? 'default' : 'secondary'}>{s.active ? 'Ativo' : 'Inativo'}</Badge>
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-24',
            render: (_: unknown, s: Supplier) => (
                <div className="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={(e) => { e.stopPropagation(); openModal(s); }}
                    >
                        <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="text-destructive"
                        onClick={(e) => { e.stopPropagation(); handleDelete(s); }}
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <MainLayout>
            <Head title="Fornecedores" />
            <div>
                <PageHeader
                    title="Fornecedores"
                    subtitle={`${suppliers.length} fornecedores cadastrados`}
                    icon={<Truck className="h-6 w-6" />}
                    actions={
                        <Button onClick={() => openModal()}>
                            <Plus className="h-4 w-4 mr-1" />
                            Novo Fornecedor
                        </Button>
                    }
                />

                <SearchFilter
                    searchValue={search}
                    onSearchChange={setSearch}
                    placeholder="Buscar por nome ou CNPJ..."
                />

                <DataTable
                    columns={columns}
                    data={filteredSuppliers}
                    keyField="id"
                    onRowClick={openModal}
                />

                <Modal
                    isOpen={isModalOpen}
                    onClose={() => setIsModalOpen(false)}
                    title={editingSupplier ? 'Editar Fornecedor' : 'Novo Fornecedor'}
                    size="lg"
                >
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Nome / Razão Social" required>
                                <Input
                                    value={formData.name}
                                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                    placeholder="Nome do fornecedor"
                                    required
                                    disabled={processing}
                                />
                            </FormField>
                            <FormField label="CNPJ" required>
                                <Input
                                    value={formData.cnpj}
                                    onChange={(e) => setFormData({ ...formData, cnpj: e.target.value })}
                                    placeholder="00.000.000/0000-00"
                                    required
                                    disabled={processing}
                                />
                            </FormField>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="E-mail">
                                <Input
                                    type="email"
                                    value={formData.email}
                                    onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                                    placeholder="email@exemplo.com"
                                    disabled={processing}
                                />
                            </FormField>
                            <FormField label="Telefone">
                                <Input
                                    value={formData.phone}
                                    onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                                    placeholder="(00) 0000-0000"
                                    disabled={processing}
                                />
                            </FormField>
                        </div>

                        <FormField label="Endereço">
                            <Textarea
                                value={formData.address}
                                onChange={(e) => setFormData({ ...formData, address: e.target.value })}
                                placeholder="Endereço completo"
                                disabled={processing}
                            />
                        </FormField>

                        <Checkbox
                            label="Fornecedor ativo"
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
                                {processing ? 'Salvando...' : (editingSupplier ? 'Salvar' : 'Cadastrar')}
                            </Button>
                        </div>
                    </form>
                </Modal>
            </div>
        </MainLayout>
    );
};

export default SuppliersPage;
