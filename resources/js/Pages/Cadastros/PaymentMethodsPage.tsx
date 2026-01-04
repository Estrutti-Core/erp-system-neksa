import React, { useState, useMemo } from 'react';
import { PaymentMethod } from '@/data/mockData';
import PageHeader from '@/Components/shared/PageHeader';
import DataTable from '@/Components/shared/DataTable';
import Modal from '@/Components/shared/Modal';
import SearchFilter from '@/Components/shared/SearchFilter';
import { FormField, Input, Select, Checkbox } from '@/Components/shared/FormComponents';
import { CreditCard, Plus, Edit, Trash2 } from 'lucide-react';
import MainLayout from '@/Components/layout/MainLayout';
import { Head, router } from '@inertiajs/react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';

interface Props {
    paymentMethods: PaymentMethod[];
}

const PaymentMethodsPage: React.FC<Props> = ({ paymentMethods }) => {
    const [search, setSearch] = useState('');
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingMethod, setEditingMethod] = useState<PaymentMethod | null>(null);
    const [formData, setFormData] = useState({
        name: '',
        type: 'cash' as string,
        active: true,
    });
    const [processing, setProcessing] = useState(false);

    const typeLabels: Record<string, string> = {
        cash: 'Dinheiro',
        credit_card: 'Crédito',
        debit_card: 'Débito',
        pix: 'PIX',
        store_credit: 'Crediário',
        other: 'Outro',
    };

    const filteredMethods = useMemo(() => {
        return paymentMethods.filter(p =>
            p.name.toLowerCase().includes(search.toLowerCase())
        );
    }, [paymentMethods, search]);

    const openModal = (method?: PaymentMethod) => {
        if (method) {
            setEditingMethod(method);
            setFormData({
                name: method.name,
                type: method.type,
                active: method.active,
            });
        } else {
            setEditingMethod(null);
            setFormData({
                name: '',
                type: 'cash',
                active: true,
            });
        }
        setIsModalOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);

        if (editingMethod) {
            router.put(`/cadastros/pagamentos/${editingMethod.id}`, formData, {
                onSuccess: () => {
                    setIsModalOpen(false);
                    setProcessing(false);
                },
                onError: () => setProcessing(false),
            });
        } else {
            router.post('/cadastros/pagamentos', formData, {
                onSuccess: () => {
                    setIsModalOpen(false);
                    setProcessing(false);
                },
                onError: () => setProcessing(false),
            });
        }
    };

    const handleDelete = (method: PaymentMethod) => {
        if (window.confirm(`Deseja excluir a forma de pagamento "${method.name}"?`)) {
            router.delete(`/cadastros/pagamentos/${method.id}`);
        }
    };

    const columns = [
        { key: 'name', header: 'Nome' },
        {
            key: 'type',
            header: 'Tipo',
            render: (_: unknown, p: PaymentMethod) => (
                <Badge variant="outline">{typeLabels[p.type] || p.type}</Badge>
            ),
        },
        {
            key: 'active',
            header: 'Status',
            render: (_: unknown, p: PaymentMethod) => (
                <Badge variant={p.active ? 'default' : 'secondary'}>{p.active ? 'Ativo' : 'Inativo'}</Badge>
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-24',
            render: (_: unknown, p: PaymentMethod) => (
                <div className="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={(e) => { e.stopPropagation(); openModal(p); }}
                    >
                        <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="text-destructive"
                        onClick={(e) => { e.stopPropagation(); handleDelete(p); }}
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <MainLayout>
            <Head title="Formas de Pagamento" />
            <div>
                <PageHeader
                    title="Formas de Pagamento"
                    subtitle={`${paymentMethods.length} formas cadastradas`}
                    icon={<CreditCard className="h-6 w-6" />}
                    actions={
                        <Button onClick={() => openModal()}>
                            <Plus className="h-4 w-4 mr-1" />
                            Nova Forma
                        </Button>
                    }
                />

                <SearchFilter
                    searchValue={search}
                    onSearchChange={setSearch}
                    placeholder="Buscar forma de pagamento..."
                />

                <DataTable
                    columns={columns}
                    data={filteredMethods}
                    keyField="id"
                    onRowClick={openModal}
                />

                <Modal
                    isOpen={isModalOpen}
                    onClose={() => setIsModalOpen(false)}
                    title={editingMethod ? 'Editar Forma de Pagamento' : 'Nova Forma de Pagamento'}
                >
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Nome" required>
                            <Input
                                value={formData.name}
                                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                placeholder="Nome da forma de pagamento"
                                required
                                disabled={processing}
                            />
                        </FormField>

                        <FormField label="Tipo" required>
                            <Select
                                value={formData.type}
                                onChange={(e) => setFormData({ ...formData, type: e.target.value })}
                                required
                                disabled={processing}
                            >
                                {Object.entries(typeLabels).map(([value, label]) => (
                                    <option key={value} value={value}>{label}</option>
                                ))}
                            </Select>
                        </FormField>

                        <Checkbox
                            label="Forma de pagamento ativa"
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
                                {processing ? 'Salvando...' : (editingMethod ? 'Salvar' : 'Cadastrar')}
                            </Button>
                        </div>
                    </form>
                </Modal>
            </div>
        </MainLayout>
    );
};

export default PaymentMethodsPage;
