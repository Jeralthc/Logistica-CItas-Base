# Guía de Cálculo de Tiempos de Descarga - Sistema de Citas

Este documento explica en detalle cómo el sistema de "Logística Citas" estima y calcula el tiempo que tomará descargar la mercancía de un proveedor en el muelle. 

El proceso consta de dos fases principales: **Estimación Inicial** (cuando los datos viajan del ERP a la Web) y el **Cálculo Definitivo** (cuando el proveedor confirma sus datos en el formulario).

---

## 1. Fase de Sincronización (ERP -> Web)

Cuando se ejecuta el comando de sincronización (`php artisan erp:sync`), el sistema se conecta a la base de datos SQL Server del ERP y extrae las Órdenes de Compra (ODC). Como en este momento no hay intervención humana, el sistema hace una estimación "ciega" basada únicamente en los datos de la factura:

### A. Detección Automática de Categoría
El sistema lee los departamentos, grupos y subgrupos de los productos en la factura y asigna una categoría sugerida:
- Si detecta Frutas, Verduras u Hortalizas asigna: **"Perecederos 3 (Frutas y Verduras)"**
- Si detecta Carnes, Lácteos, Pescadería o Congelados asigna: **"Perecederos 1 (Charcuteria)"**
- Si la factura es mayormente de productos secos, asigna el valor por defecto: **"Alimentos 1 (Viveres)"**

### B. Estimación del Peso en Toneladas
El sistema necesita unificar todas las unidades de medida (Cajas, Kilos, Unidades) en un solo valor matemático (Toneladas). Lo hace con el siguiente orden de prioridad:
1. **Suma de Kilos:** Suma todos los kilos directos que vienen en la factura para perecederos.
2. **Suma de Unidades:** Cuenta los artículos facturados como "Unidades" y asume un peso aproximado (1 UND = ~1 KG).
3. **Suma de Bultos:** Si la orden NO tiene Kilos registrados (es pura mercancía seca), suma la cantidad de Bultos/Cajas y asume un promedio de 15 KG por bulto.

Al final, suma todo, lo divide entre 1,000 y guarda el valor final como `peso_estimado_ton` (Ejemplo: `0.660` Toneladas).

---

## 2. Fase de Agendamiento (Formulario del Proveedor)

Cuando el proveedor va a agendar su cita, el sistema le abre un "Formulario de Despacho". En este punto, los valores calculados en la Fase 1 se usan solo como un "pre-llenado" para ayudar al proveedor, pero el cálculo definitivo depende de lo que el proveedor confirme en pantalla.

### Elementos que afectan el cálculo:
1. **Tipo de Mercancía Declarada:** El proveedor puede cambiar la categoría sugerida por el sistema.
2. **Peso Real (Toneladas):** El proveedor debe corregir el peso si la estimación inicial del sistema fue incorrecta.
3. **Formato de Carga:** Si el proveedor selecciona "Carga Paletizada", el sistema ignora la categoría seleccionada y usa internamente los parámetros de la categoría "Carga Paletizada General" (ya que la mercancía en paletas se descarga más rápido, independientemente de qué alimento sea).

> **Nota:** El campo **"Tipo de Vehículo"** (Sencillo, Gandola, etc.) no afecta el cálculo de tiempo matemático. Solo tiene un propósito informativo y organizativo para que el personal del muelle sepa qué transporte va a llegar.

---

## 3. La Fórmula Matemática

Cada vez que el proveedor modifica el Peso, la Categoría o el Formato de Carga, el sistema consulta los coeficientes de esa categoría en la base de datos (módulo Administrador -> Categorías) y aplica la siguiente fórmula matemática:

```text
Duración (Minutos) = Tiempo Fijo + (Peso Real ÷ Velocidad de Descarga)
```

### Componentes de la Fórmula:
- **Tiempo Fijo:** Son los minutos "muertos" que siempre se gastan sin importar el tamaño de la carga (tiempo de cuadrar el camión, abrir puertas, revisar papeles). Ej: `20` minutos.
- **Peso Real:** La cantidad en Toneladas. Ej: `1.5` Ton.
- **Velocidad de Descarga:** Es la capacidad de los operarios medida en **"Toneladas por Minuto"**. Ej: Si descargan 60 kilos por minuto, eso es `0.06` Toneladas/min.

**Ejemplo Práctico:**
Un proveedor llega con `1.5` Toneladas de Víveres.
- Tiempo Fijo de Víveres: `20` min
- Velocidad de Víveres: `0.06` ton/min

```math
Duración = 20 + ( 1.5 / 0.06 )
Duración = 20 + ( 25 )
Duración = 45 minutos
```

### Regla de Seguridad
Independientemente de lo rápido que dé el resultado matemático de la fórmula, el sistema tiene una protección en código que **nunca permitirá que una cita dure menos de 30 minutos**. Si la fórmula da 15 o 22 minutos, el sistema la redondeará automáticamente a 30 minutos para garantizar que siempre haya un margen mínimo en el calendario de los muelles.
