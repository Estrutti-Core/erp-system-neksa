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
    
    # Add import if missing
    if 'MainLayout' not in content:
        # Check specific import style. ProductsPage already imports it (relative).
        # We'll import using alias for others or check if it exists.
        # Ideally import one line after 'import React...'
        if "import MainLayout" not in content:
             content = "import MainLayout from '@/Components/layout/MainLayout';\n" + content
    
    # Wrap content
    if '<MainLayout>' not in content:
        # Regex to find 'return (' followed optionally by whitespace and then <div>'
        # We want to insert <MainLayout> before the div
        
        # Check if it starts with return ( <div
        if re.search(r'return \(\s*<div', content):
            content = re.sub(r'(return \(\s*)(<div)', r'\1<MainLayout>\n\2', content)
            
            # Now find the closing </div> );
            # This is hard to regex perfectly if there are nested divs.
            # But the structure is usually:
            # return (
            #   <div>
            #     ...
            #   </div>
            # );
            # So looking for the last </div> before ); within the function body.
            
            # Simple heuristic: Replace the last '</div>' before ');'
            # content = re.sub(r'(</div>)(\s*\);)', r'\1\n</MainLayout>\2', content)
            
            # However, if there are multiple return statements (unlikely for these top level components but possible)
            # safer to replace the LAST occurrence of '</div>\s*);' in the file.
            # These files end with 'export default ...' usually.
            
            # Find the last occurrence of '</div>\s*);'
            matches = list(re.finditer(r'(</div>)(\s*\);)', content))
            if matches:
                last_match = matches[-1]
                # Check if this looks like the end of the component
                # We can replace it.
                start, end = last_match.span()
                content = content[:start] + last_match.group(1) + "\n</MainLayout>" + last_match.group(2) + content[end:]
                
                print(f"Updated {file_path}")
                
                with open(file_path, 'w') as f:
                    f.write(content)
            else:
                print(f"Could not find closing tag for {file_path}")
        else:
            print(f"Could not find return <div> pattern for {file_path}")

