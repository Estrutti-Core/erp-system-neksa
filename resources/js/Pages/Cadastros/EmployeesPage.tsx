import React, { useState, useMemo } from 'react';
import { Employee, formatDate } from '@/data/mockData';
import PageHeader from '@/Components/shared/PageHeader';
import DataTable from '@/Components/shared/DataTable';
import Modal from '@/Components/shared/Modal';
import SearchFilter from '@/Components/shared/SearchFilter';
import { FormField, Input, Select, Checkbox } from '@/Components/shared/FormComponents';
import { UserCircle, Plus, Edit, Trash2 } from 'lucide-react';
import MainLayout from '@/Components/layout/MainLayout';
import { Head, router } from '@inertiajs/react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';

interface Props {
    employees: Employee[];
}

const EmployeesPage: React.FC<Props> = ({ employees }) => {
    const [search, setSearch] = useState('');
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingEmployee, setEditingEmployee] = useState<Employee | null>(null);
    const [formData, setFormData] = useState({
        name: '',
        document: '',
        role: '',
        email: '',
        phone: '',
        hire_date: '',
        active: true,
    });
    const [processing, setProcessing] = useState(false);

    const roles = ['Gerente', 'Operador de Caixa', 'Estoquista', 'Atendente', 'Auxiliar'];

    const filteredEmployees = useMemo(() => {
        return employees.filter(e =>
            e.name.toLowerCase().includes(search.toLowerCase()) ||
            (e.document && e.document.includes(search)) ||
            e.role.toLowerCase().includes(search.toLowerCase())
        );
    }, [employees, search]);

    const openModal = (employee?: Employee) => {
        if (employee) {
            setEditingEmployee(employee);
            setFormData({
                name: employee.name,
                document: employee.document || '',
                role: employee.role,
                email: employee.email || '',
                phone: employee.phone || '',
                hire_date: employee.hire_date || '',
                active: employee.active,
            });
        } else {
            setEditingEmployee(null);
            setFormData({
                name: '',
                document: '',
                role: roles[0],
                email: '',
                phone: '',
                hire_date: new Date().toISOString().split('T')[0],
                active: true,
            });
        }
        setIsModalOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);

        if (editingEmployee) {
            router.put(`/cadastros/funcionarios/${editingEmployee.id}`, formData, {
                onSuccess: () => {
                    setIsModalOpen(false);
                    setProcessing(false);
                },
                onError: () => setProcessing(false),
            });
        } else {
            router.post('/cadastros/funcionarios', formData, {
                onSuccess: () => {
                    setIsModalOpen(false);
                    setProcessing(false);
                },
                onError: () => setProcessing(false),
            });
        }
    };

    const handleDelete = (employee: Employee) => {
        if (window.confirm(`Deseja excluir o funcionário "${employee.name}"?`)) {
            router.delete(`/cadastros/funcionarios/${employee.id}`);
        }
    };

    const columns = [
        { key: 'name', header: 'Nome' },
        { key: 'document', header: 'CPF' },
        { key: 'role', header: 'Cargo' },
        { key: 'phone', header: 'Telefone' },
        {
            key: 'hire_date',
            header: 'Admissão',
            render: (_: unknown, e: any) => e.hire_date ? formatDate(e.hire_date) : '-',
        },
        {
            key: 'active',
            header: 'Status',
            render: (_: unknown, e: Employee) => (
                <Badge variant={e.active ? 'default' : 'secondary'}>{e.active ? 'Ativo' : 'Inativo'}</Badge>
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-24',
            render: (_: unknown, e: Employee) => (
                <div className="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={(ev) => { ev.stopPropagation(); openModal(e); }}
                    >
                        <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="text-destructive"
                        onClick={(ev) => { ev.stopPropagation(); handleDelete(e); }}
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <MainLayout>
            <Head title="Funcionários" />
            <div>
                <PageHeader
                    title="Funcionários"
                    subtitle={`${employees.length} funcionários cadastrados`}
                    icon={<UserCircle className="h-6 w-6" />}
                    actions={
                        <Button onClick={() => openModal()}>
                            <Plus className="h-4 w-4 mr-2" />
                            Novo Funcionário
                        </Button>
                    }
                />

                <SearchFilter
                    searchValue={search}
                    onSearchChange={setSearch}
                    placeholder="Buscar por nome, CPF ou cargo..."
                />

                <DataTable
                    columns={columns}
                    data={filteredEmployees}
                    keyField="id"
                    onRowClick={openModal}
                />

                <Modal
                    isOpen={isModalOpen}
                    onClose={() => setIsModalOpen(false)}
                    title={editingEmployee ? 'Editar Funcionário' : 'Novo Funcionário'}
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
                            <FormField label="CPF" required>
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
                            <FormField label="Cargo" required>
                                <Select
                                    value={formData.role}
                                    onChange={(e) => setFormData({ ...formData, role: e.target.value })}
                                    required
                                    disabled={processing}
                                >
                                    {roles.map(role => (
                                        <option key={role} value={role}>{role}</option>
                                    ))}
                                </Select>
                            </FormField>
                            <FormField label="Data de Admissão" required>
                                <Input
                                    type="date"
                                    value={formData.hire_date}
                                    onChange={(e) => setFormData({ ...formData, hire_date: e.target.value })}
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

                        <Checkbox
                            label="Funcionário ativo"
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
                                {processing ? 'Salvando...' : (editingEmployee ? 'Salvar' : 'Cadastrar')}
                            </Button>
                        </div>
                    </form>
                </Modal>
            </div>
        </MainLayout>
    );
};

export default EmployeesPage;
