import React from 'react';
import { useTheme } from '../../hooks/useTheme';
import { LogOut, User, Sun, Moon } from 'lucide-react';
import { router, usePage } from '@inertiajs/react';

const Header: React.FC = () => {
  const { auth } = usePage().props as any;
  const user = auth?.user;
  const { theme, toggleTheme } = useTheme();

  const handleLogout = () => {
    router.post(route('logout'));
  };

  const currentDate = new Date().toLocaleDateString('pt-BR', {
    weekday: 'long',
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  });

  return (
    <header className="flex h-14 items-center justify-between border-b border-border bg-card px-6">
      <div className="text-sm text-muted-foreground capitalize">{currentDate}</div>

      <div className="flex items-center gap-4">
        <button
          onClick={toggleTheme}
          className="flex items-center justify-center h-8 w-8 rounded-md text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
          title={theme === 'light' ? 'Modo escuro' : 'Modo claro'}
        >
          {theme === 'light' ? <Moon className="h-4 w-4" /> : <Sun className="h-4 w-4" />}
        </button>

        <div className="flex items-center gap-2 text-sm">
          <User className="h-4 w-4 text-muted-foreground" />
          <span className="font-medium">{user?.name}</span>
          <span className="text-muted-foreground">({user?.email})</span>
        </div>

        <button
          onClick={handleLogout}
          className="flex items-center gap-2 rounded-md px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
        >
          <LogOut className="h-4 w-4" />
          Sair
        </button>
      </div>
    </header>
  );
};

export default Header;
