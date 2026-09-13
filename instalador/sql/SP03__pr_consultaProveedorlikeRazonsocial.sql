/* ============================================================================
   SP-03 · pr_consultaProveedorlikeRazonsocial  ·  buscar proveedor por nombre
   ----------------------------------------------------------------------------
   QUE ES
     El buscador de proveedor de la guia tiene dos modos. Por RUC usa
     GetMaestroproveedoresByRuc, que ya existe en el DataMart. Por razon social
     usa ESTE procedimiento, que NO esta en todas las instalaciones.

   POR QUE HACE FALTA
     La ApiGRE lo llama en CatalogoRepository.proveedores() cuando tipo = 3:

         { call pr_consultaProveedorlikeRazonsocial(?) }

     Si no existe, el driver responde con un error que consultar() traga como
     "catalogo vacio": la busqueda por razon social devuelve una lista vacia
     SIN NINGUN ERROR EN PANTALLA. El usuario escribe el nombre del proveedor,
     no sale nada, y no hay forma de saber por que. /api/v1/health lo delata
     en procedimientosFaltantes, y el instalador lo avisa, pero la unica cura
     es crearlo.

   CONTRATO CON LA ApiGRE — NO CAMBIAR LOS NOMBRES
     La ApiGRE proyecta por nombre de columna, exactamente estas seis:

         CodProveedor · NombreProveedor · Ruc · Direccion · Telefono · CodEstacion

     Si una se renombra, llega null al navegador sin error visible. Son las
     mismas seis (y con el mismo formato) que ya devuelve el SP hermano por
     RUC, para que las dos busquedas se comporten igual.

   FORMA, TIPOS Y ORIGEN DE DATOS
     Calcados de GetMaestroproveedoresByRuc: misma tabla (MaestroProveedores),
     mismo filtro de Estado, y CodProveedor con el mismo relleno a 6 digitos
     -- RIGHT(CAST(1000000 + CodProveedor AS char(7)), 6) -- para que el
     codigo que viaja al front sea identico por los dos caminos.

     Se omiten a proposito los tres LEFT JOIN del SP por RUC (MaestroDireccion,
     MaestroDistrito, MaestroFormadePago): la ApiGRE no usa ninguna de esas
     columnas, y en una busqueda con comodin al principio cuestan caro.

   COLLATION
     La comparacion fuerza COLLATE Latin1_General_CI_AI en los dos lados, asi
     que la busqueda ignora mayusculas Y tildes sea cual sea la collation de
     la base del cliente. Sin esto, en una base con collation sensible a
     acentos "MONTANA" no encontraria "OLA Y MONTAÑA S.A.C.", y en una
     sensible a mayusculas "cotonal" no encontraria "COTONAL S.A.C.".

   RENDIMIENTO
     Una busqueda parcial es '%texto%': lleva comodin al principio, asi que
     NINGUN indice puede hacer seek. Lo que si se puede es abaratar el barrido,
     y eso se hace en tres frentes:
       1. TOP (@Tope) con ORDER BY estable: el buscador solo muestra la primera
          pagina. Corta en el servidor, antes de que las filas viajen.
       2. Solo las seis columnas necesarias, sin joins: menos ancho por fila.
       3. El indice de cobertura de mas abajo (opcional): convierte el barrido
          de la tabla entera -MaestroProveedores tiene ~28 columnas- en el
          barrido de una estructura estrecha con solo lo que se devuelve.

   RE-EJECUTABLE
     Patron IF NOT EXISTS + ALTER, igual que SP-01 y SP-02: conserva los
     permisos otorgados sobre el procedimiento, cosa que DROP + CREATE borra.
     Correrlo N veces deja el mismo resultado.
   ========================================================================== */
SET NOCOUNT ON;
GO

/* -- OBLIGATORIO ----------------------------------------------------------
   QUOTED_IDENTIFIER y ANSI_NULLS se GRABAN DENTRO del procedimiento al
   momento de crearlo, no al ejecutarlo. Se fijan aqui para que este SP quede
   grabado con las mismas opciones que los del ERP (quoted_identifier=1) y no
   herede el default de sqlcmd, que es OFF.
   ------------------------------------------------------------------------ */
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO

IF OBJECT_ID('dbo.pr_consultaProveedorlikeRazonsocial','P') IS NULL
    EXEC('CREATE PROCEDURE dbo.pr_consultaProveedorlikeRazonsocial AS SET NOCOUNT ON;');
GO

SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO

ALTER PROCEDURE [dbo].[pr_consultaProveedorlikeRazonsocial]
    /* varchar(100) = el ancho exacto de MaestroProveedores.NombreProveedor.
       Mas corto cortaria busquedas legitimas; mas largo no encuentra nada. */
    @RazonSocial varchar(100)
AS
BEGIN
    SET NOCOUNT ON;

    /* Techo de filas. El buscador de la pantalla pagina, y la ApiGRE ademas
       recorta con setMaxRows. Este es el ultimo cinturon: que una busqueda de
       una sola letra en un cliente con 40.000 proveedores no arrastre 40.000
       filas hasta el navegador. */
    DECLARE @Tope int = 200;

    DECLARE @Texto  varchar(100);
    DECLARE @Patron varchar(210);

    SET @Texto = LTRIM(RTRIM(ISNULL(@RazonSocial, '')));

    /* Los comodines que escriba el usuario se tratan como texto literal.
       Sin esto, un nombre con guion bajo o un '%' suelto cambian el sentido
       de la busqueda -- '%' solo devolveria la tabla entera. El orden importa:
       el corchete va primero, o se escaparian los corchetes que introducen
       los dos REPLACE siguientes. */
    SET @Texto = REPLACE(@Texto, '[', '[[]');
    SET @Texto = REPLACE(@Texto, '%', '[%]');
    SET @Texto = REPLACE(@Texto, '_', '[_]');

    /* Coincidencia parcial: el texto puede estar en cualquier parte del
       nombre. Es lo que espera quien busca "cotonal" y el proveedor esta
       registrado como "DISTRIBUIDORA COTONAL S.A.C.". */
    SET @Patron = '%' + @Texto + '%';

    SELECT TOP (@Tope)
           /* Mismo relleno a 6 digitos que GetMaestroproveedoresByRuc: el
              front recibe el codigo identico venga de donde venga. */
           RIGHT(CAST(1000000 + p.CodProveedor AS char(7)), 6) AS CodProveedor,
           ISNULL(p.NombreProveedor, '')                       AS NombreProveedor,
           ISNULL(p.RUC, '')                                   AS Ruc,
           ISNULL(p.Direccion, '')                             AS Direccion,
           ISNULL(p.Telefono, '')                              AS Telefono,
           p.CodEstacion                                       AS CodEstacion
      FROM dbo.MaestroProveedores AS p
     /* Estado se compara contra '1' y no contra 1: la columna es char(1) y
        compararla con un entero obliga a SQL Server a convertir la columna
        fila por fila. El SP hermano lo hace con el entero; aqui se evita. */
     WHERE p.Estado = '1'
       AND ISNULL(p.NombreProveedor, '') COLLATE Latin1_General_CI_AI
           LIKE @Patron COLLATE Latin1_General_CI_AI
     /* Orden estable y previsible: alfabetico, y CodProveedor como desempate
        para que dos proveedores homonimos no bailen entre una llamada y otra
        (sin desempate, con TOP, podrian incluso alternarse cual entra). */
     ORDER BY p.NombreProveedor, p.CodProveedor;
END
GO

PRINT 'SP-03 OK - pr_consultaProveedorlikeRazonsocial creado';
GO
