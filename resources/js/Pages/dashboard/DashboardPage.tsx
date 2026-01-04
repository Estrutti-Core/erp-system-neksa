import React from 'react';
import { formatCurrency } from '../../data/mockData';
import PageHeader from '../../Components/shared/PageHeader';
import StatCard from '../../Components/shared/StatCard';
import MainLayout from '../../Components/layout/MainLayout';
import { Head, Link } from '@inertiajs/react';
import {
  LayoutDashboard,
  ShoppingCart,
  Package,
  Users,
  AlertTriangle,
  TrendingUp,
  Wallet,
  Clock,
} from 'lucide-react';

interface Sale {
  id: string;
  sale_number: number;
  total: number;
  created_at: string;
  status: string;
  customer?: { name: string };
  payment_method?: { name: string };
}

interface Product {
  id: string;
  name: string;
  sku: string;
  barcode: string;
  stock_balance: number;
  min_stock: number;
  unit: string;
}

interface OverdueAccount {
  id: string;
  supplierName: string;
  description: string;
  amount: number;
  dueDate: string;
}

interface DashboardProps {
  stats: {
    salesToday: number;
    totalSales: number;
    totalSalesCount: number;
    productsCount: number;
    lowStockCount: number;
    customersCount: number;
    criticalStockCount: number;
  };
  recentSales: Sale[];
  lowStockProducts: Product[];
  mockFinancial: {
    pendingPayables: number;
    overduePayablesCount: number;
    pendingReceivables: number;
    overduePayables: OverdueAccount[];
  };
}

const DashboardPage: React.FC<DashboardProps> = ({ stats, recentSales, lowStockProducts, mockFinancial }) => {
  return (
    <MainLayout>
      <Head title="Dashboard" />
      <div>
        <PageHeader
          title="Dashboard"
          subtitle="Visão geral do sistema"
          icon={<LayoutDashboard className="h-6 w-6" />}
        />

        {/* Stats Grid */}
        <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
          <Link href="/vendas/lista" className="transition-transform hover:scale-[1.02]">
            <StatCard
              title="Vendas Hoje"
              value={formatCurrency(stats.salesToday)}
              icon={ShoppingCart}
              variant="default"
            />
          </Link>
          <Link href="/relatorios/vendas" className="transition-transform hover:scale-[1.02]">
            <StatCard
              title="Total de Vendas"
              value={formatCurrency(stats.totalSales)}
              subtitle={`${stats.totalSalesCount} vendas enviadas`}
              icon={TrendingUp}
              variant="success"
            />
          </Link>
          <Link href="/cadastros/produtos" className="transition-transform hover:scale-[1.02]">
            <StatCard
              title="Produtos Cadastrados"
              value={stats.productsCount}
              subtitle={`${stats.lowStockCount} com estoque baixo`}
              icon={Package}
              variant="default"
            />
          </Link>
          <Link href="/cadastros/clientes" className="transition-transform hover:scale-[1.02]">
            <StatCard
              title="Clientes Ativos"
              value={stats.customersCount}
              icon={Users}
              variant="default"
            />
          </Link>
        </div>

        {/* Secondary Stats */}
        <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
          <Link href="/financeiro/pagar" className="transition-transform hover:scale-[1.02]">
            <StatCard
              title="Contas a Pagar"
              value={formatCurrency(mockFinancial.pendingPayables)}
              subtitle={`${mockFinancial.overduePayablesCount} vencidas (MOCK)`}
              icon={Wallet}
              variant={mockFinancial.overduePayablesCount > 0 ? 'destructive' : 'warning'}
            />
          </Link>
          <Link href="/financeiro/receber" className="transition-transform hover:scale-[1.02]">
            <StatCard
              title="Contas a Receber"
              value={formatCurrency(mockFinancial.pendingReceivables)}
              subtitle="(MOCK)"
              icon={TrendingUp}
              variant="success"
            />
          </Link>
          <Link href="/relatorios/estoque-critico" className="transition-transform hover:scale-[1.02]">
            <StatCard
              title="Estoque Crítico"
              value={stats.criticalStockCount}
              subtitle="Produtos precisam reposição"
              icon={AlertTriangle}
              variant={stats.criticalStockCount > 0 ? 'destructive' : 'default'}
            />
          </Link>
        </div>

        {/* Content Grid */}
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          {/* Recent Sales */}
          <div className="erp-card">
            <div className="flex items-center justify-between border-b border-border px-5 py-4">
              <h3 className="font-medium text-foreground">Vendas Recentes</h3>
              <Link href="/vendas/lista" className="text-xs text-primary hover:underline">Ver todas</Link>
            </div>
            <div className="divide-y divide-border">
              {recentSales.length === 0 ? (
                <div className="px-5 py-8 text-center text-muted-foreground">
                  Nenhuma venda registrada
                </div>
              ) : (
                recentSales.map(sale => (
                  <div key={sale.id} className="flex items-center justify-between px-5 py-3">
                    <div>
                      <p className="text-sm font-medium text-foreground">
                        #{String(sale.sale_number).padStart(6, '0')} - {sale.customer?.name || 'Consumidor'}
                      </p>
                      <p className="text-xs text-muted-foreground">
                        {new Date(sale.created_at).toLocaleString('pt-BR')}
                      </p>
                    </div>
                    <div className="text-right">
                      <p className="text-sm font-medium text-foreground">
                        {formatCurrency(sale.total)}
                      </p>
                      <p className="text-xs text-muted-foreground">{sale.payment_method?.name}</p>
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>

          {/* Low Stock Alert */}
          <div className="erp-card">
            <div className="flex items-center justify-between border-b border-border px-5 py-4">
              <h3 className="font-medium text-foreground">Alertas de Estoque</h3>
              <span className="erp-badge-warning">{stats.lowStockCount} itens</span>
            </div>
            <div className="divide-y divide-border">
              {lowStockProducts.length === 0 ? (
                <div className="px-5 py-8 text-center text-muted-foreground">
                  Todos os produtos com estoque adequado
                </div>
              ) : (
                lowStockProducts.map(product => (
                  <div key={product.id} className="flex items-center justify-between px-5 py-3">
                    <div>
                      <p className="text-sm font-medium text-foreground">{product.name}</p>
                      <p className="text-xs text-muted-foreground">Código: {product.sku || product.barcode}</p>
                    </div>
                    <div className="text-right">
                      <p className={`text-sm font-medium ${product.stock_balance <= product.min_stock * 0.5
                        ? 'text-destructive'
                        : 'text-warning'
                        }`}>
                        {product.stock_balance} {product.unit}
                      </p>
                      <p className="text-xs text-muted-foreground">
                        Mín: {product.min_stock} {product.unit}
                      </p>
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>

          {/* Overdue Accounts */}
          <div className="erp-card">
            <div className="flex items-center justify-between border-b border-border px-5 py-4">
              <h3 className="font-medium text-foreground">Contas Vencidas (MOCK)</h3>
              <Clock className="h-4 w-4 text-muted-foreground" />
            </div>
            <div className="divide-y divide-border">
              {mockFinancial.overduePayables.length === 0 ? (
                <div className="px-5 py-8 text-center text-muted-foreground">
                  Nenhuma conta vencida
                </div>
              ) : (
                mockFinancial.overduePayables.map(account => (
                  <div key={account.id} className="flex items-center justify-between px-5 py-3">
                    <div>
                      <p className="text-sm font-medium text-foreground">{account.supplierName}</p>
                      <p className="text-xs text-muted-foreground">{account.description}</p>
                    </div>
                    <div className="text-right">
                      <p className="text-sm font-medium text-destructive">
                        {formatCurrency(account.amount)}
                      </p>
                      <p className="text-xs text-muted-foreground">
                        Venceu: {new Date(account.dueDate).toLocaleDateString('pt-BR')}
                      </p>
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>

          {/* Quick Actions */}
          <div className="erp-card">
            <div className="border-b border-border px-5 py-4">
              <h3 className="font-medium text-foreground">Ações Rápidas</h3>
            </div>
            <div className="grid grid-cols-2 gap-3 p-5">
              <Link
                href="/cadastros/produtos"
                className="erp-btn-outline flex-col gap-2 py-6"
              >
                <Package className="h-6 w-6 text-primary" />
                <span>Novo Produto</span>
              </Link>
              <Link
                href="/estoque/entrada"
                className="erp-btn-outline flex-col gap-2 py-6"
              >
                <TrendingUp className="h-6 w-6 text-success" />
                <span>Entrada de Estoque</span>
              </Link>
              <Link
                href="/vendas/lista"
                className="erp-btn-outline flex-col gap-2 py-6"
              >
                <ShoppingCart className="h-6 w-6 text-primary" />
                <span>Ver Vendas</span>
              </Link>
              <Link
                href="/relatorios/estoque-critico"
                className="erp-btn-outline flex-col gap-2 py-6"
              >
                <AlertTriangle className="h-6 w-6 text-warning" />
                <span>Estoque Crítico</span>
              </Link>
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
};

export default DashboardPage;
