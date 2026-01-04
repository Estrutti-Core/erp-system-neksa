// Mock Data for ERP System
// All data is stored in memory and can be modified during runtime

export interface Category {
  id: string;
  name: string;
  description: string;
  active: boolean;
}

export interface Product {
  id: string;
  code: string;
  name: string;
  categoryId: string;
  supplierId: string;
  costPrice: number;
  salePrice: number;
  stock: number;
  minStock: number;
  unit: string;
  active: boolean;
  createdAt: string;
}

export interface Supplier {
  id: string;
  name: string;
  cnpj: string;
  email: string;
  phone: string;
  address: string;
  active: boolean;
}

export interface Customer {
  id: string;
  name: string;
  cpf: string;
  email: string;
  phone: string;
  address: string;
  active: boolean;
  createdAt: string;
}

export interface Employee {
  id: string;
  name: string;
  cpf: string;
  role: string;
  email: string;
  phone: string;
  active: boolean;
  hireDate: string;
}

export interface PaymentMethod {
  id: string;
  name: string;
  type: 'cash' | 'credit' | 'debit' | 'pix' | 'boleto';
  active: boolean;
}

export interface StockMovement {
  id: string;
  productId: string;
  type: 'entry' | 'exit' | 'adjustment';
  quantity: number;
  reason: string;
  date: string;
  userId: string;
}

export interface SaleItem {
  productId: string;
  productName: string;
  quantity: number;
  unitPrice: number;
  total: number;
}

export interface Sale {
  id: string;
  customerId: string | null;
  customerName: string;
  employeeId: string;
  employeeName: string;
  paymentMethodId: string;
  paymentMethodName: string;
  items: SaleItem[];
  subtotal: number;
  discount: number;
  total: number;
  status: 'completed' | 'cancelled' | 'pending';
  date: string;
}

export interface AccountPayable {
  id: string;
  supplierId: string;
  supplierName: string;
  description: string;
  amount: number;
  dueDate: string;
  paidDate: string | null;
  status: 'pending' | 'paid' | 'overdue';
}

export interface AccountReceivable {
  id: string;
  customerId: string;
  customerName: string;
  description: string;
  amount: number;
  dueDate: string;
  paidDate: string | null;
  status: 'pending' | 'paid' | 'overdue';
}

// Initial mock data
export const initialCategories: Category[] = [
  { id: '1', name: 'Bebidas', description: 'Refrigerantes, sucos, águas', active: true },
  { id: '2', name: 'Laticínios', description: 'Leites, queijos, iogurtes', active: true },
  { id: '3', name: 'Padaria', description: 'Pães, bolos, biscoitos', active: true },
  { id: '4', name: 'Hortifruti', description: 'Frutas, verduras, legumes', active: true },
  { id: '5', name: 'Carnes', description: 'Bovinas, suínas, aves', active: true },
  { id: '6', name: 'Limpeza', description: 'Produtos de limpeza doméstica', active: true },
  { id: '7', name: 'Higiene', description: 'Higiene pessoal', active: true },
  { id: '8', name: 'Mercearia', description: 'Arroz, feijão, massas, conservas', active: true },
];

export const initialSuppliers: Supplier[] = [
  { id: '1', name: 'Distribuidora ABC', cnpj: '12.345.678/0001-90', email: 'contato@abc.com', phone: '(11) 3456-7890', address: 'Rua das Flores, 123 - São Paulo/SP', active: true },
  { id: '2', name: 'Bebidas Brasil Ltda', cnpj: '23.456.789/0001-01', email: 'vendas@bebidasbrasil.com', phone: '(11) 2345-6789', address: 'Av. Industrial, 456 - Guarulhos/SP', active: true },
  { id: '3', name: 'Laticínios Serra', cnpj: '34.567.890/0001-12', email: 'comercial@serra.com', phone: '(19) 3456-7891', address: 'Rod. dos Bandeirantes, km 80 - Campinas/SP', active: true },
  { id: '4', name: 'Frigorífico Central', cnpj: '45.678.901/0001-23', email: 'vendas@frigocentral.com', phone: '(11) 4567-8901', address: 'Av. dos Estados, 789 - Santo André/SP', active: true },
  { id: '5', name: 'Atacado Limpeza Total', cnpj: '56.789.012/0001-34', email: 'atacado@limpezatotal.com', phone: '(11) 5678-9012', address: 'Rua Comercial, 321 - Osasco/SP', active: true },
];

export const initialProducts: Product[] = [
  { id: '1', code: 'BEB001', name: 'Coca-Cola 2L', categoryId: '1', supplierId: '2', costPrice: 5.50, salePrice: 8.99, stock: 150, minStock: 30, unit: 'UN', active: true, createdAt: '2024-01-15' },
  { id: '2', code: 'BEB002', name: 'Guaraná Antarctica 2L', categoryId: '1', supplierId: '2', costPrice: 4.80, salePrice: 7.99, stock: 120, minStock: 25, unit: 'UN', active: true, createdAt: '2024-01-15' },
  { id: '3', code: 'LAT001', name: 'Leite Integral 1L', categoryId: '2', supplierId: '3', costPrice: 3.20, salePrice: 5.49, stock: 200, minStock: 50, unit: 'UN', active: true, createdAt: '2024-01-16' },
  { id: '4', code: 'LAT002', name: 'Queijo Mussarela kg', categoryId: '2', supplierId: '3', costPrice: 28.00, salePrice: 42.90, stock: 25, minStock: 10, unit: 'KG', active: true, createdAt: '2024-01-16' },
  { id: '5', code: 'PAD001', name: 'Pão Francês kg', categoryId: '3', supplierId: '1', costPrice: 8.00, salePrice: 14.90, stock: 50, minStock: 20, unit: 'KG', active: true, createdAt: '2024-01-17' },
  { id: '6', code: 'CAR001', name: 'Picanha kg', categoryId: '5', supplierId: '4', costPrice: 55.00, salePrice: 79.90, stock: 15, minStock: 5, unit: 'KG', active: true, createdAt: '2024-01-18' },
  { id: '7', code: 'CAR002', name: 'Frango Inteiro kg', categoryId: '5', supplierId: '4', costPrice: 12.00, salePrice: 18.90, stock: 40, minStock: 15, unit: 'KG', active: true, createdAt: '2024-01-18' },
  { id: '8', code: 'LIM001', name: 'Detergente 500ml', categoryId: '6', supplierId: '5', costPrice: 1.50, salePrice: 2.99, stock: 300, minStock: 50, unit: 'UN', active: true, createdAt: '2024-01-19' },
  { id: '9', code: 'LIM002', name: 'Água Sanitária 1L', categoryId: '6', supplierId: '5', costPrice: 2.00, salePrice: 3.99, stock: 180, minStock: 40, unit: 'UN', active: true, createdAt: '2024-01-19' },
  { id: '10', code: 'MER001', name: 'Arroz 5kg', categoryId: '8', supplierId: '1', costPrice: 18.00, salePrice: 27.90, stock: 80, minStock: 20, unit: 'UN', active: true, createdAt: '2024-01-20' },
  { id: '11', code: 'MER002', name: 'Feijão Carioca 1kg', categoryId: '8', supplierId: '1', costPrice: 6.50, salePrice: 9.90, stock: 100, minStock: 30, unit: 'UN', active: true, createdAt: '2024-01-20' },
  { id: '12', code: 'HOR001', name: 'Banana kg', categoryId: '4', supplierId: '1', costPrice: 3.00, salePrice: 5.99, stock: 8, minStock: 15, unit: 'KG', active: true, createdAt: '2024-01-21' },
  { id: '13', code: 'HOR002', name: 'Tomate kg', categoryId: '4', supplierId: '1', costPrice: 4.50, salePrice: 8.99, stock: 5, minStock: 10, unit: 'KG', active: true, createdAt: '2024-01-21' },
  { id: '14', code: 'HIG001', name: 'Sabonete 90g', categoryId: '7', supplierId: '5', costPrice: 1.20, salePrice: 2.49, stock: 250, minStock: 60, unit: 'UN', active: true, createdAt: '2024-01-22' },
  { id: '15', code: 'BEB003', name: 'Água Mineral 500ml', categoryId: '1', supplierId: '2', costPrice: 0.80, salePrice: 1.99, stock: 400, minStock: 100, unit: 'UN', active: true, createdAt: '2024-01-22' },
];

export const initialCustomers: Customer[] = [
  { id: '1', name: 'João Silva', cpf: '123.456.789-00', email: 'joao@email.com', phone: '(11) 98765-4321', address: 'Rua A, 100 - São Paulo/SP', active: true, createdAt: '2024-01-10' },
  { id: '2', name: 'Maria Santos', cpf: '234.567.890-11', email: 'maria@email.com', phone: '(11) 97654-3210', address: 'Av. B, 200 - São Paulo/SP', active: true, createdAt: '2024-01-12' },
  { id: '3', name: 'Pedro Oliveira', cpf: '345.678.901-22', email: 'pedro@email.com', phone: '(11) 96543-2109', address: 'Rua C, 300 - Guarulhos/SP', active: true, createdAt: '2024-01-14' },
  { id: '4', name: 'Ana Costa', cpf: '456.789.012-33', email: 'ana@email.com', phone: '(11) 95432-1098', address: 'Av. D, 400 - Osasco/SP', active: true, createdAt: '2024-01-16' },
  { id: '5', name: 'Carlos Ferreira', cpf: '567.890.123-44', email: 'carlos@email.com', phone: '(11) 94321-0987', address: 'Rua E, 500 - São Paulo/SP', active: true, createdAt: '2024-01-18' },
];

export const initialEmployees: Employee[] = [
  { id: '1', name: 'Roberto Administrador', cpf: '111.222.333-44', role: 'Gerente', email: 'roberto@mercado.com', phone: '(11) 91111-1111', active: true, hireDate: '2020-01-05' },
  { id: '2', name: 'Fernanda Caixa', cpf: '222.333.444-55', role: 'Operador de Caixa', email: 'fernanda@mercado.com', phone: '(11) 92222-2222', active: true, hireDate: '2021-03-10' },
  { id: '3', name: 'Lucas Estoque', cpf: '333.444.555-66', role: 'Estoquista', email: 'lucas@mercado.com', phone: '(11) 93333-3333', active: true, hireDate: '2022-06-15' },
  { id: '4', name: 'Juliana Atendente', cpf: '444.555.666-77', role: 'Atendente', email: 'juliana@mercado.com', phone: '(11) 94444-4444', active: true, hireDate: '2023-01-20' },
];

export const initialPaymentMethods: PaymentMethod[] = [
  { id: '1', name: 'Dinheiro', type: 'cash', active: true },
  { id: '2', name: 'Cartão de Crédito', type: 'credit', active: true },
  { id: '3', name: 'Cartão de Débito', type: 'debit', active: true },
  { id: '4', name: 'PIX', type: 'pix', active: true },
  { id: '5', name: 'Boleto', type: 'boleto', active: true },
];

export const initialStockMovements: StockMovement[] = [
  { id: '1', productId: '1', type: 'entry', quantity: 100, reason: 'Compra de fornecedor', date: '2024-12-20', userId: '3' },
  { id: '2', productId: '3', type: 'entry', quantity: 150, reason: 'Compra de fornecedor', date: '2024-12-21', userId: '3' },
  { id: '3', productId: '12', type: 'adjustment', quantity: -5, reason: 'Perda por validade', date: '2024-12-22', userId: '1' },
  { id: '4', productId: '6', type: 'entry', quantity: 20, reason: 'Compra de fornecedor', date: '2024-12-23', userId: '3' },
  { id: '5', productId: '8', type: 'entry', quantity: 200, reason: 'Compra de fornecedor', date: '2024-12-24', userId: '3' },
];

export const initialSales: Sale[] = [
  {
    id: '1',
    customerId: '1',
    customerName: 'João Silva',
    employeeId: '2',
    employeeName: 'Fernanda Caixa',
    paymentMethodId: '4',
    paymentMethodName: 'PIX',
    items: [
      { productId: '1', productName: 'Coca-Cola 2L', quantity: 2, unitPrice: 8.99, total: 17.98 },
      { productId: '10', productName: 'Arroz 5kg', quantity: 1, unitPrice: 27.90, total: 27.90 },
    ],
    subtotal: 45.88,
    discount: 0,
    total: 45.88,
    status: 'completed',
    date: '2024-12-27T10:30:00',
  },
  {
    id: '2',
    customerId: '2',
    customerName: 'Maria Santos',
    employeeId: '2',
    employeeName: 'Fernanda Caixa',
    paymentMethodId: '2',
    paymentMethodName: 'Cartão de Crédito',
    items: [
      { productId: '6', productName: 'Picanha kg', quantity: 2, unitPrice: 79.90, total: 159.80 },
      { productId: '3', productName: 'Leite Integral 1L', quantity: 6, unitPrice: 5.49, total: 32.94 },
      { productId: '5', productName: 'Pão Francês kg', quantity: 1, unitPrice: 14.90, total: 14.90 },
    ],
    subtotal: 207.64,
    discount: 7.64,
    total: 200.00,
    status: 'completed',
    date: '2024-12-27T14:15:00',
  },
  {
    id: '3',
    customerId: null,
    customerName: 'Cliente Avulso',
    employeeId: '2',
    employeeName: 'Fernanda Caixa',
    paymentMethodId: '1',
    paymentMethodName: 'Dinheiro',
    items: [
      { productId: '15', productName: 'Água Mineral 500ml', quantity: 3, unitPrice: 1.99, total: 5.97 },
      { productId: '14', productName: 'Sabonete 90g', quantity: 2, unitPrice: 2.49, total: 4.98 },
    ],
    subtotal: 10.95,
    discount: 0,
    total: 10.95,
    status: 'completed',
    date: '2024-12-27T16:45:00',
  },
  {
    id: '4',
    customerId: '3',
    customerName: 'Pedro Oliveira',
    employeeId: '2',
    employeeName: 'Fernanda Caixa',
    paymentMethodId: '3',
    paymentMethodName: 'Cartão de Débito',
    items: [
      { productId: '7', productName: 'Frango Inteiro kg', quantity: 3, unitPrice: 18.90, total: 56.70 },
      { productId: '11', productName: 'Feijão Carioca 1kg', quantity: 2, unitPrice: 9.90, total: 19.80 },
      { productId: '12', productName: 'Banana kg', quantity: 2, unitPrice: 5.99, total: 11.98 },
    ],
    subtotal: 88.48,
    discount: 0,
    total: 88.48,
    status: 'completed',
    date: '2024-12-28T09:20:00',
  },
  {
    id: '5',
    customerId: '4',
    customerName: 'Ana Costa',
    employeeId: '2',
    employeeName: 'Fernanda Caixa',
    paymentMethodId: '4',
    paymentMethodName: 'PIX',
    items: [
      { productId: '8', productName: 'Detergente 500ml', quantity: 5, unitPrice: 2.99, total: 14.95 },
      { productId: '9', productName: 'Água Sanitária 1L', quantity: 3, unitPrice: 3.99, total: 11.97 },
    ],
    subtotal: 26.92,
    discount: 0,
    total: 26.92,
    status: 'completed',
    date: '2024-12-28T11:00:00',
  },
];

export const initialAccountsPayable: AccountPayable[] = [
  { id: '1', supplierId: '2', supplierName: 'Bebidas Brasil Ltda', description: 'NF 12345 - Compra de bebidas', amount: 2500.00, dueDate: '2024-12-30', paidDate: null, status: 'pending' },
  { id: '2', supplierId: '3', supplierName: 'Laticínios Serra', description: 'NF 67890 - Compra de laticínios', amount: 1800.00, dueDate: '2024-12-28', paidDate: null, status: 'overdue' },
  { id: '3', supplierId: '4', supplierName: 'Frigorífico Central', description: 'NF 11111 - Compra de carnes', amount: 4200.00, dueDate: '2025-01-05', paidDate: null, status: 'pending' },
  { id: '4', supplierId: '1', supplierName: 'Distribuidora ABC', description: 'NF 22222 - Compra de mercearia', amount: 3100.00, dueDate: '2024-12-20', paidDate: '2024-12-19', status: 'paid' },
  { id: '5', supplierId: '5', supplierName: 'Atacado Limpeza Total', description: 'NF 33333 - Compra de limpeza', amount: 950.00, dueDate: '2025-01-10', paidDate: null, status: 'pending' },
];

export const initialAccountsReceivable: AccountReceivable[] = [
  { id: '1', customerId: '5', customerName: 'Carlos Ferreira', description: 'Venda a prazo - Dez/24', amount: 450.00, dueDate: '2024-12-30', paidDate: null, status: 'pending' },
  { id: '2', customerId: '1', customerName: 'João Silva', description: 'Venda a prazo - Nov/24', amount: 280.00, dueDate: '2024-12-15', paidDate: '2024-12-14', status: 'paid' },
  { id: '3', customerId: '3', customerName: 'Pedro Oliveira', description: 'Venda a prazo - Dez/24', amount: 620.00, dueDate: '2025-01-05', paidDate: null, status: 'pending' },
];

// Helper function to generate unique IDs
export const generateId = (): string => {
  return Date.now().toString(36) + Math.random().toString(36).substr(2);
};

// Format currency
export const formatCurrency = (value: number): string => {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format(value);
};

// Format date
export const formatDate = (dateString: string): string => {
  if (!dateString) return '-';
  const date = new Date(dateString);
  if (isNaN(date.getTime())) return '-';
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(date);
};

// Format date with time
export const formatDateTime = (dateString: string): string => {
  if (!dateString) return '-';
  const date = new Date(dateString);
  if (isNaN(date.getTime())) return '-';
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date);
};
