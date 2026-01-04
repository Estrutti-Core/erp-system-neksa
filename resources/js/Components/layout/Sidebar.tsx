import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
  LayoutDashboard,
  Package,
  ShoppingCart,
  Wallet,
  BarChart3,
  FolderOpen,
  Users,
  Truck,
  UserCircle,
  CreditCard,
  Tags,
  Boxes,
  ClipboardList,
  ArrowUpDown,
  FileText,
  Receipt,
  TrendingUp,
  AlertTriangle,
  ChevronDown,
  ChevronRight,
  Store,
} from 'lucide-react';

interface MenuItem {
  label: string;
  path?: string;
  icon: React.ElementType;
  children?: { label: string; path: string; icon: React.ElementType }[];
}

const menuItems: MenuItem[] = [
  { label: 'Dashboard', path: '/dashboard', icon: LayoutDashboard },
  {
    label: 'Cadastros',
    icon: FolderOpen,
    children: [
      { label: 'Produtos', path: '/cadastros/produtos', icon: Package },
      { label: 'Categorias', path: '/cadastros/categorias', icon: Tags },
      { label: 'Fornecedores', path: '/cadastros/fornecedores', icon: Truck },
      { label: 'Clientes', path: '/cadastros/clientes', icon: Users },
      { label: 'Funcionários', path: '/cadastros/funcionarios', icon: UserCircle },
      { label: 'Formas de Pagamento', path: '/cadastros/pagamentos', icon: CreditCard },
    ],
  },
  {
    label: 'Estoque',
    icon: Boxes,
    children: [
      { label: 'Consulta de Estoque', path: '/estoque/consulta', icon: ClipboardList },
      { label: 'Entrada de Mercadoria', path: '/estoque/entrada', icon: ArrowUpDown },
      { label: 'Ajuste de Estoque', path: '/estoque/ajuste', icon: FileText },
      { label: 'Movimentações', path: '/estoque/movimentacoes', icon: Receipt },
    ],
  },
  {
    label: 'Vendas',
    icon: ShoppingCart,
    children: [
      { label: 'Lista de Vendas', path: '/vendas/lista', icon: ClipboardList },
    ],
  },
  {
    label: 'Financeiro',
    icon: Wallet,
    children: [
      { label: 'Contas a Pagar', path: '/financeiro/pagar', icon: Receipt },
      { label: 'Contas a Receber', path: '/financeiro/receber', icon: TrendingUp },
      { label: 'Fluxo de Caixa', path: '/financeiro/fluxo', icon: BarChart3 },
    ],
  },
  {
    label: 'Relatórios',
    icon: BarChart3,
    children: [
      { label: 'Vendas por Período', path: '/relatorios/vendas', icon: TrendingUp },
      { label: 'Produtos Mais Vendidos', path: '/relatorios/produtos', icon: Package },
      { label: 'Estoque Crítico', path: '/relatorios/estoque-critico', icon: AlertTriangle },
    ],
  },
];

const Sidebar: React.FC = () => {
  const { url } = usePage();
  const [expandedMenus, setExpandedMenus] = React.useState<string[]>(['Cadastros', 'Estoque', 'Vendas', 'Financeiro', 'Relatórios']);

  const toggleMenu = (label: string) => {
    setExpandedMenus(prev =>
      prev.includes(label) ? prev.filter(l => l !== label) : [...prev, label]
    );
  };

  const isActive = (path: string) => url === path || url.startsWith(path + '/');
  const isChildActive = (children?: MenuItem['children']) =>
    children?.some(child => url === child.path || url.startsWith(child.path + '/'));

  return (
    <aside className="flex h-full w-60 flex-col bg-sidebar text-sidebar-foreground">
      {/* Logo */}
      <div className="flex h-14 items-center gap-2 border-b border-sidebar-border px-4">
        <Store className="h-6 w-6 text-sidebar-primary" />
        <span className="text-lg font-semibold text-sidebar-accent-foreground">Mercado ERP</span>
      </div>

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto py-4">
        <ul className="space-y-1 px-2">
          {menuItems.map(item => (
            <li key={item.label}>
              {item.path ? (
                <Link
                  href={item.path}
                  className={`flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors ${isActive(item.path)
                    ? 'bg-sidebar-primary text-sidebar-primary-foreground'
                    : 'text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'
                    }`}
                >
                  <item.icon className="h-4 w-4" />
                  {item.label}
                </Link>
              ) : (
                <>
                  <button
                    onClick={() => toggleMenu(item.label)}
                    className={`flex w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors ${isChildActive(item.children)
                      ? 'text-sidebar-accent-foreground'
                      : 'text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'
                      }`}
                  >
                    <div className="flex items-center gap-3">
                      <item.icon className="h-4 w-4" />
                      {item.label}
                    </div>
                    {expandedMenus.includes(item.label) ? (
                      <ChevronDown className="h-4 w-4" />
                    ) : (
                      <ChevronRight className="h-4 w-4" />
                    )}
                  </button>
                  {expandedMenus.includes(item.label) && item.children && (
                    <ul className="ml-4 mt-1 space-y-1 border-l border-sidebar-border pl-3">
                      {item.children.map(child => (
                        <li key={child.path}>
                          <Link
                            href={child.path}
                            className={`flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors ${isActive(child.path)
                              ? 'bg-sidebar-primary text-sidebar-primary-foreground'
                              : 'text-sidebar-muted hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'
                              }`}
                          >
                            <child.icon className="h-4 w-4" />
                            {child.label}
                          </Link>
                        </li>
                      ))}
                    </ul>
                  )}
                </>
              )}
            </li>
          ))}
        </ul>
      </nav>

      {/* Footer */}
      <div className="border-t border-sidebar-border p-4">
        <p className="text-xs text-sidebar-muted">v1.0.0 - Mercado System</p>
      </div>
    </aside>
  );
};

export default Sidebar;

