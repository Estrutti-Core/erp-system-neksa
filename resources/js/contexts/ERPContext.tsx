import React, { createContext, useContext, useState, ReactNode } from 'react';
import {
  Category,
  Product,
  Supplier,
  Customer,
  Employee,
  PaymentMethod,
  StockMovement,
  Sale,
  AccountPayable,
  AccountReceivable,
  initialCategories,
  initialProducts,
  initialSuppliers,
  initialCustomers,
  initialEmployees,
  initialPaymentMethods,
  initialStockMovements,
  initialSales,
  initialAccountsPayable,
  initialAccountsReceivable,
  generateId,
} from '../data/mockData';

interface User {
  id: string;
  name: string;
  email: string;
  role: string;
}

interface ERPContextType {
  // Auth
  user: User | null;
  isAuthenticated: boolean;
  login: (email: string, password: string) => boolean;
  logout: () => void;

  // Categories
  categories: Category[];
  addCategory: (category: Omit<Category, 'id'>) => void;
  updateCategory: (id: string, category: Partial<Category>) => void;
  deleteCategory: (id: string) => void;

  // Products
  products: Product[];
  addProduct: (product: Omit<Product, 'id' | 'createdAt'>) => void;
  updateProduct: (id: string, product: Partial<Product>) => void;
  deleteProduct: (id: string) => void;

  // Suppliers
  suppliers: Supplier[];
  addSupplier: (supplier: Omit<Supplier, 'id'>) => void;
  updateSupplier: (id: string, supplier: Partial<Supplier>) => void;
  deleteSupplier: (id: string) => void;

  // Customers
  customers: Customer[];
  addCustomer: (customer: Omit<Customer, 'id' | 'createdAt'>) => void;
  updateCustomer: (id: string, customer: Partial<Customer>) => void;
  deleteCustomer: (id: string) => void;

  // Employees
  employees: Employee[];
  addEmployee: (employee: Omit<Employee, 'id'>) => void;
  updateEmployee: (id: string, employee: Partial<Employee>) => void;
  deleteEmployee: (id: string) => void;

  // Payment Methods
  paymentMethods: PaymentMethod[];
  addPaymentMethod: (paymentMethod: Omit<PaymentMethod, 'id'>) => void;
  updatePaymentMethod: (id: string, paymentMethod: Partial<PaymentMethod>) => void;
  deletePaymentMethod: (id: string) => void;

  // Stock
  stockMovements: StockMovement[];
  addStockMovement: (movement: Omit<StockMovement, 'id' | 'date' | 'userId'>) => void;

  // Sales
  sales: Sale[];
  addSale: (sale: Omit<Sale, 'id' | 'date'>) => void;
  updateSale: (id: string, sale: Partial<Sale>) => void;

  // Financial
  accountsPayable: AccountPayable[];
  addAccountPayable: (account: Omit<AccountPayable, 'id'>) => void;
  updateAccountPayable: (id: string, account: Partial<AccountPayable>) => void;

  accountsReceivable: AccountReceivable[];
  addAccountReceivable: (account: Omit<AccountReceivable, 'id'>) => void;
  updateAccountReceivable: (id: string, account: Partial<AccountReceivable>) => void;
}

const ERPContext = createContext<ERPContextType | undefined>(undefined);

export const ERPProvider: React.FC<{ children: ReactNode }> = ({ children }) => {
  // Auth state
  const [user, setUser] = useState<User | null>(null);
  const [isAuthenticated, setIsAuthenticated] = useState(false);

  // Data states
  const [categories, setCategories] = useState<Category[]>(initialCategories);
  const [products, setProducts] = useState<Product[]>(initialProducts);
  const [suppliers, setSuppliers] = useState<Supplier[]>(initialSuppliers);
  const [customers, setCustomers] = useState<Customer[]>(initialCustomers);
  const [employees, setEmployees] = useState<Employee[]>(initialEmployees);
  const [paymentMethods, setPaymentMethods] = useState<PaymentMethod[]>(initialPaymentMethods);
  const [stockMovements, setStockMovements] = useState<StockMovement[]>(initialStockMovements);
  const [sales, setSales] = useState<Sale[]>(initialSales);
  const [accountsPayable, setAccountsPayable] = useState<AccountPayable[]>(initialAccountsPayable);
  const [accountsReceivable, setAccountsReceivable] = useState<AccountReceivable[]>(initialAccountsReceivable);

  // Auth functions
  const login = (email: string, password: string): boolean => {
    // Mock login - accepts any email with password "123456"
    if (password === '123456' && email) {
      setUser({
        id: '1',
        name: 'Roberto Administrador',
        email: email,
        role: 'Gerente',
      });
      setIsAuthenticated(true);
      return true;
    }
    return false;
  };

  const logout = () => {
    setUser(null);
    setIsAuthenticated(false);
  };

  // Category functions
  const addCategory = (category: Omit<Category, 'id'>) => {
    setCategories([...categories, { ...category, id: generateId() }]);
  };

  const updateCategory = (id: string, category: Partial<Category>) => {
    setCategories(categories.map(c => c.id === id ? { ...c, ...category } : c));
  };

  const deleteCategory = (id: string) => {
    setCategories(categories.filter(c => c.id !== id));
  };

  // Product functions
  const addProduct = (product: Omit<Product, 'id' | 'createdAt'>) => {
    setProducts([...products, {
      ...product,
      id: generateId(),
      createdAt: new Date().toISOString().split('T')[0]
    }]);
  };

  const updateProduct = (id: string, product: Partial<Product>) => {
    setProducts(products.map(p => p.id === id ? { ...p, ...product } : p));
  };

  const deleteProduct = (id: string) => {
    setProducts(products.filter(p => p.id !== id));
  };

  // Supplier functions
  const addSupplier = (supplier: Omit<Supplier, 'id'>) => {
    setSuppliers([...suppliers, { ...supplier, id: generateId() }]);
  };

  const updateSupplier = (id: string, supplier: Partial<Supplier>) => {
    setSuppliers(suppliers.map(s => s.id === id ? { ...s, ...supplier } : s));
  };

  const deleteSupplier = (id: string) => {
    setSuppliers(suppliers.filter(s => s.id !== id));
  };

  // Customer functions
  const addCustomer = (customer: Omit<Customer, 'id' | 'createdAt'>) => {
    setCustomers([...customers, {
      ...customer,
      id: generateId(),
      createdAt: new Date().toISOString().split('T')[0]
    }]);
  };

  const updateCustomer = (id: string, customer: Partial<Customer>) => {
    setCustomers(customers.map(c => c.id === id ? { ...c, ...customer } : c));
  };

  const deleteCustomer = (id: string) => {
    setCustomers(customers.filter(c => c.id !== id));
  };

  // Employee functions
  const addEmployee = (employee: Omit<Employee, 'id'>) => {
    setEmployees([...employees, { ...employee, id: generateId() }]);
  };

  const updateEmployee = (id: string, employee: Partial<Employee>) => {
    setEmployees(employees.map(e => e.id === id ? { ...e, ...employee } : e));
  };

  const deleteEmployee = (id: string) => {
    setEmployees(employees.filter(e => e.id !== id));
  };

  // Payment Method functions
  const addPaymentMethod = (paymentMethod: Omit<PaymentMethod, 'id'>) => {
    setPaymentMethods([...paymentMethods, { ...paymentMethod, id: generateId() }]);
  };

  const updatePaymentMethod = (id: string, paymentMethod: Partial<PaymentMethod>) => {
    setPaymentMethods(paymentMethods.map(p => p.id === id ? { ...p, ...paymentMethod } : p));
  };

  const deletePaymentMethod = (id: string) => {
    setPaymentMethods(paymentMethods.filter(p => p.id !== id));
  };

  // Stock functions
  const addStockMovement = (movement: Omit<StockMovement, 'id' | 'date' | 'userId'>) => {
    const newMovement: StockMovement = {
      ...movement,
      id: generateId(),
      date: new Date().toISOString(),
      userId: user?.id || '1',
    };
    setStockMovements([...stockMovements, newMovement]);

    // Update product stock
    setProducts(products.map(p => {
      if (p.id === movement.productId) {
        const newStock = movement.type === 'exit'
          ? p.stock - Math.abs(movement.quantity)
          : p.stock + movement.quantity;
        return { ...p, stock: Math.max(0, newStock) };
      }
      return p;
    }));
  };

  // Sales functions
  const addSale = (sale: Omit<Sale, 'id' | 'date'>) => {
    const newSale: Sale = {
      ...sale,
      id: generateId(),
      date: new Date().toISOString(),
    };
    setSales([...sales, newSale]);

    // Update product stock for sold items
    sale.items.forEach(item => {
      setProducts(prev => prev.map(p => {
        if (p.id === item.productId) {
          return { ...p, stock: Math.max(0, p.stock - item.quantity) };
        }
        return p;
      }));
    });
  };

  const updateSale = (id: string, sale: Partial<Sale>) => {
    setSales(sales.map(s => s.id === id ? { ...s, ...sale } : s));
  };

  // Financial functions
  const addAccountPayable = (account: Omit<AccountPayable, 'id'>) => {
    setAccountsPayable([...accountsPayable, { ...account, id: generateId() }]);
  };

  const updateAccountPayable = (id: string, account: Partial<AccountPayable>) => {
    setAccountsPayable(accountsPayable.map(a => a.id === id ? { ...a, ...account } : a));
  };

  const addAccountReceivable = (account: Omit<AccountReceivable, 'id'>) => {
    setAccountsReceivable([...accountsReceivable, { ...account, id: generateId() }]);
  };

  const updateAccountReceivable = (id: string, account: Partial<AccountReceivable>) => {
    setAccountsReceivable(accountsReceivable.map(a => a.id === id ? { ...a, ...account } : a));
  };

  return (
    <ERPContext.Provider value={{
      user,
      isAuthenticated,
      login,
      logout,
      categories,
      addCategory,
      updateCategory,
      deleteCategory,
      products,
      addProduct,
      updateProduct,
      deleteProduct,
      suppliers,
      addSupplier,
      updateSupplier,
      deleteSupplier,
      customers,
      addCustomer,
      updateCustomer,
      deleteCustomer,
      employees,
      addEmployee,
      updateEmployee,
      deleteEmployee,
      paymentMethods,
      addPaymentMethod,
      updatePaymentMethod,
      deletePaymentMethod,
      stockMovements,
      addStockMovement,
      sales,
      addSale,
      updateSale,
      accountsPayable,
      addAccountPayable,
      updateAccountPayable,
      accountsReceivable,
      addAccountReceivable,
      updateAccountReceivable,
    }}>
      {children}
    </ERPContext.Provider>
  );
};

export const useERP = (): ERPContextType => {
  const context = useContext(ERPContext);
  if (!context) {
    throw new Error('useERP must be used within an ERPProvider');
  }
  return context;
};
