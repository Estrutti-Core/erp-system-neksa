import os
import re

pages_dir = 'resources/js/Pages'
exclude_dirs = ['Auth']
exclude_files = ['Notfound.tsx', 'NotFound.jsx', 'Welcome.jsx', 'Dashboard.jsx'] # Already handled or deleted

def process_file(file_path):
    with open(file_path, 'r') as f:
        content = f.read()

    if 'MainLayout' not in content:
        # Add import
        import_line = "import MainLayout from '@/Components/layout/MainLayout';"
        # Handle relative paths if necessary or just use alias which is set up
        # The file currently uses relative imports in some places, but alias is better if configured.
        # My previous edit to DashboardPage used '../../Components/layout/MainLayout'.
        # I should probably use the alias '@' if it works, or calculate relative path.
        # The alias '@' is configured in vite.config.js to 'resources/js'.
        # So '@/' should work.
        
        # But wait, existing imports in ProductsPage use relative '../..'.
        # I'll stick to what works or use relative. 
        # Actually, let's try to use the existing import style or just alias which is cleaner.
        # Check if alias works (I configured it in vite.config.js).
        
        # Only add if not imported.
        content = import_line + '\n' + content

    if '<MainLayout>' not in content:
        # Wrap return
        # Regex to find the return statement or the component body
        # This is tricky with regex. 
        # However, looking at the code, it usually starts with return ( <div> or similar.
        # I will do a simple replacement of 'return (' with 'return (\n<MainLayout>' and closing tag.
        
        # But wait, 'return (' handles the start. The end is '  );\n};'.
        # This is risky doing with simple string replacement for all files.
        # Most files follow the pattern 'return (\n    <div>'.
        
        content = re.sub(r'return \(\s*<div', 'return (\n    <MainLayout>\n      <div', content, count=1)
        content = re.sub(r'\n\s*\);\n};', '\n    </MainLayout>\n  );\n};', content, count=1)
        
        # Fallback for simpler returns or differences
        # If the file wasn't changed by specific div replacement, maybe try generic component wrapping?
        # Let's inspect a few files first or correct ProductsPage manually to see pattern.
        pass

    # Actually, simpler approach for now:
    # Just list files that need MainLayout and I will edit them with replace_file_content 
    # if there are only a few.
    print(file_path)

for root, dirs, files in os.walk(pages_dir):
    # Exclude dirs
    dirs[:] = [d for d in dirs if d not in exclude_dirs]
    
    for file in files:
        if file.endswith(('.tsx', '.jsx')) and file not in exclude_files:
             # Check if it's strictly a page that needs layout
             path = os.path.join(root, file)
             with open(path, 'r') as f:
                 c = f.read()
                 if '<MainLayout>' not in c and 'GuestLayout' not in c:
                     print(path)

