import os
import re

files_to_process = [
    'resources/js/Pages/relatorios/CriticalStockPage.tsx',
    'resources/js/Pages/vendas/SalesListPage.tsx',
    'resources/js/Pages/financeiro/AccountsPayablePage.tsx',
    'resources/js/Pages/financeiro/AccountsReceivablePage.tsx',
    'resources/js/Pages/cadastros/PaymentMethodsPage.tsx',
    'resources/js/Pages/cadastros/ProductsPage.tsx',
    'resources/js/Pages/cadastros/SuppliersPage.tsx',
    'resources/js/Pages/cadastros/CustomersPage.tsx',
    'resources/js/Pages/cadastros/CategoriesPage.tsx',
    'resources/js/Pages/cadastros/EmployeesPage.tsx',
    'resources/js/Pages/estoque/StockEntryPage.tsx',
    'resources/js/Pages/estoque/StockQueryPage.tsx'
]

for file_path in files_to_process:
    with open(file_path, 'r') as f:
        content = f.read()
    
    # Find all start indices of <MainLayout>
    matches = [m.start() for m in re.finditer(r'<MainLayout>', content)]
    
    if len(matches) > 1:
        print(f"Fixing {file_path} - found {len(matches)} MainLayout tags")
        # Keep the first one. Remove the others.
        # Since removing changes indices, it's easier to reconstruct strings or use replacement with function.
        
        # We want to replace matching occurrences skipping the first one.
        
        def replace_callback(match, counter=[0]):
            counter[0] += 1
            if counter[0] > 1:
                return "" # Remove subsequent MainLayouts
            return match.group(0) # Keep first
            
        content = re.sub(r'<MainLayout>\n?', replace_callback, content)
        
        # Also check for duplicate imports?
        # My previous script checked 'if MainLayout not in content' so likely only one import.
        
        with open(file_path, 'w') as f:
            f.write(content)
    else:
        print(f"Skipping {file_path} - correct count")

