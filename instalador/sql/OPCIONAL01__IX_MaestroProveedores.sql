/* ============================================================================
   OPCIONAL - Indice de apoyo para la busqueda de proveedor por razon social
   ============================================================================
   Este archivo NO se aplica junto con los procedimientos, y a proposito.
   Los archivos SP*.sql solo crean objetos propios del sistema de guias. Este
   toca una tabla del ERP del cliente, que no es nuestra, y crear un indice
   toma un bloqueo de esquema: en una tabla grande eso se hace fuera de
   horario y con el visto bueno de quien administra el ERP.

   Aplicarlo es opcional: SP03 funciona igual con indice o sin el. Lo que
   cambia es cuanto lee. Medido en la base de laboratorio: 8 lecturas logicas
   con indice contra 16 sin el. Un LIKE '%texto%' nunca permite busqueda
   directa; lo que hace el indice es que el barrido recorra una estructura
   estrecha con solo las columnas que se devuelven, en vez de la tabla
   completa (MaestroProveedores tiene alrededor de 28 columnas, varias de
   texto largo).

   Reversible sin tocar datos:
       DROP INDEX IX_MaestroProveedores_Estado_Nombre ON dbo.MaestroProveedores;
   ============================================================================ */

IF NOT EXISTS (SELECT 1 FROM sys.indexes
                WHERE object_id = OBJECT_ID('dbo.MaestroProveedores')
                  AND name = 'IX_MaestroProveedores_Estado_Nombre')
BEGIN
    CREATE NONCLUSTERED INDEX IX_MaestroProveedores_Estado_Nombre
        ON dbo.MaestroProveedores (Estado, NombreProveedor)
        INCLUDE (CodProveedor, RUC, Direccion, Telefono, CodEstacion);
END
GO


PRINT 'Indice IX_MaestroProveedores_Estado_Nombre listo';
GO
