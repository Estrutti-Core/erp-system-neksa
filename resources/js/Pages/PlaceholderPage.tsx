import React from 'react';
import MainLayout from '@/Components/layout/MainLayout';

const PlaceholderPage: React.FC<{ title?: string }> = ({ title = 'Em Desenvolvimento' }) => {
    return (
        <MainLayout>
            <div className="flex items-center justify-center h-64">
                <div className="text-center">
                    <h2 className="text-xl font-semibold text-foreground mb-2">{title}</h2>
                    <p className="text-muted-foreground">Módulo em desenvolvimento</p>
                </div>
            </div>
        </MainLayout>
    );
};

export default PlaceholderPage;
