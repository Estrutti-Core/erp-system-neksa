import React, { useState, useMemo } from 'react';
import { Customer, formatDate } from '@/data/mockData';
import PageHeader from '@/Components/shared/PageHeader';
import DataTable from '@/Components/shared/DataTable';
import Modal from '@/Components/shared/Modal';
import SearchFilter from '@/Components/shared/SearchFilter';
import { FormField, Input, Textarea, Checkbox } from '@/Components/shared/FormComponents';
import { Users, Plus, Edit, Trash2 } from 'lucide-react';
import MainLayout from '@/Components/layout/MainLayout';
import { Head, router } from '@inertiajs/react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';

interface Props {
    customers: Customer[];
}

const CustomersPage: React.FC<Props> = ({ customers }) => {
    const [search, setSearch] = useState('');
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingCustomer, setEditingCustomer] = useState<Customer | null>(null);
    const [formData, setFormData] = useState({
        name: '',
        document: '',
        email: '',
        phone: '',
        address: '',
        active: true,
    });
    const [processing, setProcessing] = useState(false);

    const filteredCustomers = useMemo(() => {
        return customers.filter(c =>
            c.name.toLowerCase().includes(search.toLowerCase()) ||
            (c.document && c.document.includes(search))
        );
    }, [customers, search]);

    const openModal = (customer?: Customer) => {
        if (customer) {
            setEditingCustomer(customer);
            setFormData({
                name: customer.name,
                document: customer.document || '',
                email: customer.email || '',
                phone: customer.phone || '',
                address: customer.address || '',
                active: customer.active,
            });
        } else {
            setEditingCustomer(null);
            setFormData({
                name: '',
                document: '',
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

        if (editingCustomer) {
            router.put(`/cadastros/clientes/${editingCustomer.id}`, formData, {
                onSuccess: () => {
                    setIsModalOpen(false);
                    setProcessing(false);
                },
                onError: () => setProcessing(false),
            });
        } else {
            router.post('/cadastros/clientes', formData, {
                onSuccess: () => {
                    setIsModalOpen(false);
                    setProcessing(false);
                },
                onError: () => setProcessing(false),
            });
        }
    };

    const handleDelete = (customer: Customer) => {
        if (window.confirm(`Deseja excluir o cliente "${customer.name}"?`)) {
            router.delete(`/cadastros/clientes/${customer.id}`);
        }
    };

    const columns = [
        { key: 'name', header: 'Nome' },
        { key: 'document', header: 'CPF/CNPJ' },
        { key: 'phone', header: 'Telefone' },
        { key: 'email', header: 'E-mail' },
        {
            key: 'created_at',
            header: 'Cadastro',
            render: (_: unknown, c: any) => c.created_at ? formatDate(c.created_at) : '-',
        },
        {
            key: 'active',
            header: 'Status',
            render: (_: unknown, c: Customer) => (
                <Badge variant={c.active ? 'default' : 'secondary'}>{c.active ? 'Ativo' : 'Inativo'}</Badge>
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-24',
            render: (_: unknown, c: Customer) => (
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
            <Head title="Clientes" />
            <div>
                <PageHeader
                    title="Clientes"
                    subtitle={`${customers.length} clientes cadastrados`}
                    icon={<Users className="h-6 w-6" />}
                    actions={
                        <Button onClick={() => openModal()}>
                            <Plus className="h-4 w-4 mr-2" />
                            Novo Cliente
                        </Button>
                    }
                />

                <SearchFilter
                    searchValue={search}
                    onSearchChange={setSearch}
                    placeholder="Buscar por nome ou CPF..."
                />

                <DataTable
                    columns={columns}
                    data={filteredCustomers}
                    keyField="id"
                    onRowClick={openModal}
                />

                <Modal
                    isOpen={isModalOpen}
                    onClose={() => setIsModalOpen(false)}
                    title={editingCustomer ? 'Editar Cliente' : 'Novo Cliente'}
                    size="lg"
                >
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Nome" required>
                                <Input
                                    value={formData.name}
                                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                    placeholder="Nome completo"
                                    required
                                    disabled={processing}
                                />
                            </FormField>
                            <FormField label="CPF/CNPJ" required>
                                <Input
                                    value={formData.document}
                                    onChange={(e) => setFormData({ ...formData, document: e.target.value })}
                                    placeholder="000.000.000-00"
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
                                    placeholder="(00) 00000-0000"
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
                            label="Cliente ativo"
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
                                {processing ? 'Salvando...' : (editingCustomer ? 'Salvar' : 'Cadastrar')}
                            </Button>
                        </div>
                    </form>
                </Modal>
            </div>
        </MainLayout>
    );
};

export default CustomersPage;
