# SOLARFIX
## Flujos de Atención al Cliente
**Documento de Procesos — Versión 1.0**

---

### Leyenda de Actores
Cada paso de los flujos indica el actor responsable de ejecutarlo[cite: 2]:
* **RECEPCIONISTA**[cite: 2]
* **TÉCNICO**[cite: 2]
* **SISTEMA**[cite: 2]
* **CLIENTE**[cite: 2]

> **Nota:** Los flujos también incluyen tablas de decisión que muestran ramificaciones según condiciones específicas[cite: 2].

---

## Flujo 1 — Recepción de Equipo en Taller
Este flujo inicia cuando el cliente llega al taller con un equipo para reparar[cite: 2]. Puede ser en persona o por derivación previa (cita agendada)[cite: 2].

**Estados involucrados:** `—` $\rightarrow$ `RECIBIDO` $\rightarrow$ `EN DIAGNÓSTICO`[cite: 2]

### PASO 1: Bienvenida e identificación del cliente
> **Actor Responsable:** RECEPCIONISTA[cite: 2]

1. Saludar al cliente y preguntar si ya fue atendido antes[cite: 2].
2. Buscar al cliente en el sistema por número de teléfono o nombre[cite: 2].
3. **Si existe:** Confirmar datos[cite: 2]. **Si no existe:** Crear registro nuevo con nombre, teléfono/WhatsApp, email y dirección[cite: 2].
4. Confirmar el número de WhatsApp para notificaciones automáticas[cite: 2].

### PASO 2: Registro del equipo
> **Actor Responsable:** RECEPCIONISTA[cite: 2]

1. Seleccionar tipo de equipo: celular, tablet, PC, aire split, aire central, otro[cite: 2].
2. Ingresar marca, modelo e IMEI / número de serie[cite: 2].
3. Registrar accesorios que entrega el cliente (funda, cargador, cable, caja, auriculares)[cite: 2].
4. Describir el estado físico del equipo al recibir (rayones, golpes, pantalla rota, etc.)[cite: 2].
5. Registrar la falla declarada por el cliente con sus palabras exactas[cite: 2].

### PASO 3: Fotografías de recepción
> **Actor Responsable:** RECEPCIONISTA[cite: 2]

1. Tomar mínimo 4 fotos: frente, reverso, laterales y detalle del daño visible[cite: 2].
2. Cargar las fotos en la orden desde la cámara del dispositivo o archivo[cite: 2].
3. Verificar que las fotos queden asociadas correctamente a la orden antes de continuar[cite: 2].

### PASO 4: Checklist de recepción
> **Actor Responsable:** RECEPCIONISTA[cite: 2]

1. Completar el checklist del tipo de equipo correspondiente[cite: 2].
2. Marcar cada ítem como: **OK / Con daño / No aplica**[cite: 2].
3. El checklist protege al taller de reclamos sobre daños preexistentes[cite: 2].
4. Registrar el patrón de desbloqueo o PIN si el cliente lo proporciona (se almacena cifrado)[cite: 2].

### PASO 5: Firma digital de recepción
> **Actores Responsables:** RECEPCIONISTA / CLIENTE[cite: 2]

1. Mostrar al cliente el resumen de la orden: equipo, estado físico, accesorios y falla declarada[cite: 2].
2. Solicitar al cliente que firme en pantalla para confirmar que los datos son correctos[cite: 2].
3. La firma queda vinculada a la orden y aparece en el PDF[cite: 2].

### PASO 6: Estimación inicial y cierre de recepción
> **Actores Responsables:** RECEPCIONISTA / SISTEMA[cite: 2]

1. Informar al cliente un rango de tiempo estimado para el diagnóstico[cite: 2].
2. Comunicar que recibirá un WhatsApp cuando el diagnóstico esté listo[cite: 2].
3. Guardar la orden[cite: 2]. El sistema asigna número automático y cambia el estado a **RECIBIDO**[cite: 2].
4. El sistema envía un WhatsApp al cliente confirmando la recepción del equipo[cite: 2].

#### 🔀 Tabla de Decisión: ¿El cliente deja seña / anticipo?
| Condición | Acción Requerida |
| :--- | :--- |
| **Sí** | Registrar el pago parcial en la orden[cite: 2]. Imprimir o enviar comprobante provisional[cite: 2]. |
| **No** | El saldo queda pendiente completo[cite: 2]. Se cobra en la entrega[cite: 2]. |

---

## Flujo 2 — Diagnóstico y Presupuesto
El técnico recibe la orden asignada, realiza el diagnóstico técnico y elabora el presupuesto[cite: 2]. El cliente decide si autoriza la reparación[cite: 2].

**Estados involucrados:** `RECIBIDO` $\rightarrow$ `EN DIAGNÓSTICO` $\rightarrow$ `ESPERANDO APROBACIÓN` $\rightarrow$ `EN REPARACIÓN`[cite: 2]

### PASO 1: Revisión de la orden asignada
> **Actor Responsable:** TÉCNICO[cite: 2]

1. El técnico abre su lista de órdenes asignadas en el sistema[cite: 2].
2. Leer la falla declarada por el cliente y el checklist de recepción[cite: 2].
3. Revisar las fotografías de ingreso para conocer el estado previo del equipo[cite: 2].
4. Cambiar estado de la orden a **EN DIAGNÓSTICO**[cite: 2].

### PASO 2: Diagnóstico técnico
> **Actor Responsable:** TÉCNICO[cite: 2]

1. Realizar el diagnóstico completo del equipo[cite: 2].
2. Completar el checklist de diagnóstico técnico del sistema[cite: 2].
3. Identificar los repuestos necesarios y verificar disponibilidad en inventario[cite: 2].
4. Registrar en el campo 'Diagnóstico' el hallazgo técnico con detalle[cite: 2].

### PASO 3: Elaboración del presupuesto
> **Actor Responsable:** TÉCNICO[cite: 2]

1. Agregar los repuestos necesarios desde el inventario (precio de venta automático)[cite: 2].
2. Ingresar el costo de la mano de obra[cite: 2].
3. El sistema calcula el total, aplica recargo si corresponde y muestra el saldo pendiente[cite: 2].
4. Definir la fecha estimada de entrega si se aprueba la reparación[cite: 2].

### PASO 4: Notificación al cliente
> **Actores Responsables:** SISTEMA / RECEPCIONISTA[cite: 2]

1. El sistema genera un mensaje de WhatsApp con el diagnóstico y el presupuesto[cite: 2].
2. El técnico o recepcionista presiona 'Enviar por WhatsApp' para despachar el mensaje[cite: 2].
3. El estado de la orden pasa a **ESPERANDO APROBACIÓN DEL CLIENTE**[cite: 2].

#### 🔀 Tabla de Decisión: ¿El cliente aprueba el presupuesto?
| Condición | Acción Requerida |
| :--- | :--- |
| **Aprueba** | Cambiar estado a **EN REPARACIÓN**[cite: 2]. Continuar con el Flujo 3[cite: 2]. |
| **Rechaza** | El cliente retira el equipo sin reparación[cite: 2]. Ver sub-flujo de cierre sin reparación[cite: 2]. |
| **No responde en 72h** | El sistema alerta al técnico[cite: 2]. Se intenta contacto telefónico[cite: 2]. Si no hay respuesta en 5 días hábiles, se aplica política de abandono[cite: 2]. |

### Sub-flujo: Cierre sin reparación
1. Registrar en la orden el motivo: rechazó presupuesto / no hubo respuesta[cite: 2].
2. Si se realizó diagnóstico con costo: cobrar el valor de diagnóstico antes de la entrega[cite: 2].
3. El cliente firma digitalmente la devolución del equipo[cite: 2].
4. Cambiar estado de la orden a **CERRADO SIN REPARACIÓN**[cite: 2].

---

## Flujo 3 — Reparación del Equipo
El técnico ejecuta la reparación aprobada, usa repuestos del inventario y registra el trabajo realizado[cite: 2].

**Estados involucrados:** `EN REPARACIÓN` $\rightarrow$ `LISTO PARA ENTREGAR`[cite: 2]

### PASO 1: Inicio de la reparación
> **Actores Responsables:** TÉCNICO / SISTEMA[cite: 2]

1. El técnico confirma en el sistema que inicia la reparación[cite: 2].
2. Retirar los repuestos necesarios del inventario físico[cite: 2].
3. El sistema descuenta automáticamente el stock de los repuestos al guardar[cite: 2].
4. Si el stock de algún repuesto queda por debajo del mínimo, el sistema genera una alerta[cite: 2].

### PASO 2: Ejecución del trabajo
> **Actor Responsable:** TÉCNICO[cite: 2]

1. Realizar la reparación según el diagnóstico aprobado[cite: 2].
2. Tomar fotos del proceso si el trabajo es complejo o tiene garantía de repuesto[cite: 2].
3. **Si se descubre un daño adicional no contemplado:** Pausar y notificar al cliente antes de continuar[cite: 2].
4. Registrar en el campo 'Trabajo realizado' una descripción técnica de lo que se hizo[cite: 2].

#### 🔀 Tabla de Decisión: ¿Apareció un daño adicional durante la reparación?
| Condición | Acción Requerida |
| :--- | :--- |
| **Sí** | Pausar la reparación[cite: 2]. Crear un presupuesto adicional y notificar al cliente por WhatsApp[cite: 2]. Esperar aprobación antes de continuar[cite: 2]. |
| **No** | Continuar con la reparación normalmente hasta completarla[cite: 2]. |

### PASO 3: Control de calidad post-reparación
> **Actor Responsable:** TÉCNICO[cite: 2]

1. Verificar que la falla reportada quede resuelta[cite: 2].
2. Completar el checklist de prueba post-reparación[cite: 2].
3. Tomar fotos del equipo reparado (estado de entrega)[cite: 2].
4. Si el equipo no quedó completamente funcional: registrar observación y decidir si se reintenta o se notifica al cliente[cite: 2].

### PASO 4: Marcar como listo
> **Actores Responsables:** TÉCNICO / SISTEMA[cite: 2]

1. Cambiar estado de la orden a **LISTO PARA ENTREGAR**[cite: 2].
2. El sistema envía automáticamente un WhatsApp al cliente notificando que el equipo está listo[cite: 2].
3. El equipo se coloca en el área de equipos listos con su número de orden visible[cite: 2].

---

## Flujo 4 — Entrega del Equipo al Cliente
El cliente retira el equipo, se verifica el funcionamiento, se cobra el saldo pendiente y se emite la garantía[cite: 2].

**Estados involucrados:** `LISTO PARA ENTREGAR` $\rightarrow$ `ENTREGADO`[cite: 2]

### PASO 1: Identificación y búsqueda de la orden
> **Actor Responsable:** RECEPCIONISTA[cite: 2]

1. El cliente se presenta en el taller[cite: 2].
2. Buscar la orden por número, nombre del cliente o teléfono[cite: 2].
3. Confirmar que el estado de la orden es **LISTO PARA ENTREGAR**[cite: 2].
4. Retirar el equipo del área de listos[cite: 2].

### PASO 2: Verificación con el cliente
> **Actores Responsables:** RECEPCIONISTA / TÉCNICO / CLIENTE[cite: 2]

1. Mostrar el equipo al cliente y verificar su funcionamiento juntos[cite: 2].
2. Revisar que los accesorios entregados coincidan con los registrados en la orden[cite: 2].
3. El cliente confirma que la falla fue resuelta[cite: 2].
4. Explicar brevemente qué se hizo y qué garantía cubre[cite: 2].

### PASO 3: Cobro del saldo
> **Actores Responsables:** RECEPCIONISTA / CLIENTE[cite: 2]

1. Mostrar el resumen financiero: total, anticipo recibido y saldo pendiente[cite: 2].
2. Registrar la forma de pago: efectivo, transferencia, tarjeta o combinado[cite: 2].
3. El sistema actualiza el saldo a $0 y registra el pago[cite: 2].
4. Si el cliente paga con transferencia: verificar el comprobante antes de entregar el equipo[cite: 2].

### PASO 4: Firma digital de entrega
> **Actores Responsables:** RECEPCIONISTA / CLIENTE[cite: 2]

1. El cliente firma en pantalla confirmando que recibió el equipo conforme[cite: 2].
2. La firma queda registrada en la orden con timestamp[cite: 2].
3. Cambiar el estado de la orden a **ENTREGADO**[cite: 2].

### PASO 5: Emisión de documentos
> **Actor Responsable:** SISTEMA[cite: 2]

1. El sistema genera automáticamente la garantía con fecha de vencimiento[cite: 2].
2. Generar el PDF de la orden / recibo con detalle de trabajo, repuestos, cobro y firma[cite: 2].
3. Enviar el PDF al cliente por WhatsApp o imprimir según prefiera[cite: 2].
4. El WhatsApp de cierre incluye el detalle de la garantía y el número de orden para referencia futura[cite: 2].

#### 🔀 Tabla de Decisión: ¿El cliente tiene objeción sobre la reparación al momento de retirar?
| Condición | Acción Requerida |
| :--- | :--- |
| **Falla resuelta pero hay daño estético nuevo** | Documentar[cite: 2]. Si el daño no estaba en las fotos de recepción, ofrecer solución[cite: 2]. Si estaba documentado, mostrar las fotos al cliente[cite: 2]. |
| **La falla no fue resuelta** | No cobrar[cite: 2]. Devolver a técnico para revisión[cite: 2]. Crear nueva tarea interna vinculada a la orden[cite: 2]. |
| **Todo conforme** | Proceder con la entrega normalmente[cite: 2]. |

---

## Flujo 5 — Reclamo de Garantía
El cliente regresa con el equipo dentro del período de garantía alegando que la misma falla reapareció o que surgió un problema derivado de la reparación[cite: 2].

**Estados involucrados:** `ENTREGADO` $\rightarrow$ `EN GARANTÍA` $\rightarrow$ `EN REPARACIÓN` $\rightarrow$ `ENTREGADO`[cite: 2]

### PASO 1: Verificación de la garantía
> **Actor Responsable:** RECEPCIONISTA[cite: 2]

1. Buscar la orden original del cliente en el sistema[cite: 2].
2. Verificar que la garantía esté vigente (estado: **VIGENTE** o **POR VENCER**)[cite: 2].
3. Leer el tipo de garantía: mano de obra, repuesto, o servicio completo[cite: 2].
4. Registrar la falla que reporta el cliente en el reclamo[cite: 2].

#### 🔀 Tabla de Decisión: ¿La garantía está vigente?
| Condición | Acción Requerida |
| :--- | :--- |
| **Sí, vigente** | Continuar con el proceso de reclamo[cite: 2]. No se cobra al cliente[cite: 2]. |
| **Vencida** | Informar al cliente[cite: 2]. Se puede ofrecer un descuento por ser cliente frecuente, pero la reparación tiene costo[cite: 2]. Abrir una nueva orden normal[cite: 2]. |
| **No aplica (daño externo)** | Si el daño es por golpe, caída o líquido posterior a la entrega, la garantía no cubre[cite: 2]. Abrir nueva orden con costo[cite: 2]. |

### PASO 2: Apertura de orden de garantía
> **Actor Responsable:** RECEPCIONISTA[cite: 2]

1. Crear una nueva orden vinculada a la orden original[cite: 2].
2. El sistema marca la orden automáticamente como **ORDEN DE GARANTÍA**[cite: 2].
3. El costo de mano de obra y repuestos (según tipo de garantía) se registra como $0[cite: 2].
4. Registrar la descripción del reclamo y tomar fotos del estado actual[cite: 2].

### PASO 3: Reparación en garantía
> **Actor Responsable:** TÉCNICO[cite: 2]

1. El técnico revisa la falla reportada[cite: 2].
2. **Si es el mismo problema:** Reparar sin costo, descontando el repuesto a proveedor o registrándolo como pérdida interna[cite: 2].
3. **Si es un daño diferente:** Evaluar si aplica garantía o se debe presupuestar[cite: 2].
4. Registrar el diagnóstico y trabajo realizado en la orden de garantía[cite: 2].

### PASO 4: Entrega y cierre de garantía
> **Actores Responsables:** RECEPCIONISTA / SISTEMA[cite: 2]

1. Seguir el Flujo 4 de entrega normalmente[cite: 2].
2. Si se entrega sin costo: el PDF indica claramente `'REPARACIÓN EN GARANTÍA — $0'`[cite: 2].
3. El sistema registra el reclamo de garantía en el historial de la orden original[cite: 2].
4. Si se usó el reclamo de garantía, evaluar si se emite una nueva garantía sobre el trabajo realizado[cite: 2].

---

## Flujo 6 — Instalación / Visita Domiciliaria
Aplica principalmente para servicios de aire acondicionado: instalación nueva, mantenimiento preventivo, recarga de gas o revisión en sitio[cite: 2].

### PASO 1: Agendamiento de la cita
> **Actores Responsables:** RECEPCIONISTA / SISTEMA[cite: 2]

1. El cliente solicita una visita (por teléfono, WhatsApp o en persona)[cite: 2].
2. Crear una cita en la Agenda indicando: tipo de servicio, dirección, fecha, hora y técnico asignado[cite: 2].
3. Registrar notas relevantes: modelo del equipo, problema reportado, piso, acceso[cite: 2].
4. El sistema envía un WhatsApp de confirmación de cita al cliente con fecha y hora[cite: 2].

### PASO 2: Preparación del técnico
> **Actores Responsables:** TÉCNICO / SISTEMA[cite: 2]

1. El técnico revisa su agenda del día en el sistema[cite: 2].
2. Verificar en el inventario que cuenta con los materiales necesarios (gas refrigerante, filtros, etc.)[cite: 2].
3. El sistema envía un recordatorio automático de la cita al cliente el día anterior[cite: 2].

### PASO 3: Ejecución del servicio en sitio
> **Actor Responsable:** TÉCNICO[cite: 2]

1. El técnico llega al domicilio[cite: 2]. Crear la orden de servicio desde el sistema (puede hacerlo el técnico desde su dispositivo móvil)[cite: 2].
2. Completar el checklist de diagnóstico/instalación para aires acondicionados[cite: 2].
3. Tomar fotos del equipo antes y después del servicio[cite: 2].
4. Ejecutar el servicio: instalación, limpieza, recarga de gas, mantenimiento, etc[cite: 2].
5. Registrar el trabajo realizado y los materiales usados (descontados del inventario)[cite: 2].

### PASO 4: Cobro y documentación en sitio
> **Actores Responsables:** TÉCNICO / CLIENTE / SISTEMA[cite: 2]

1. Mostrar al cliente el resumen del servicio y el costo total[cite: 2].
2. Registrar el pago (efectivo o transferencia)[cite: 2].
3. El cliente firma digitalmente desde el celular del técnico[cite: 2].
4. El sistema genera la garantía y el PDF que puede enviarse por WhatsApp en el momento[cite: 2].

#### 🔀 Tabla de Decisión: ¿El servicio requiere una segunda visita?
| Condición | Acción Requerida |
| :--- | :--- |
| **Sí (falta repuesto, requiere seguimiento)** | Agendar segunda visita desde la misma orden[cite: 2]. Registrar como pendiente[cite: 2]. |
| **No** | Cerrar la orden[cite: 2]. Estado: **ENTREGADO**[cite: 2]. |

---

## Flujo 7 — Entrada de Repuestos al Inventario
Cuando el técnico o administrador compra repuestos y necesita registrarlos en el sistema para que estén disponibles para las órdenes[cite: 2].

### PASO 1: Registro de la compra
> **Actores Responsables:** ADMINISTRADOR / TÉCNICO[cite: 2]

1. Ir al módulo de **Inventario $\rightarrow$ Entradas**[cite: 2].
2. Buscar el repuesto por nombre, código o categoría[cite: 2].
3. **Si el repuesto ya existe:** Seleccionarlo y registrar la cantidad comprada junto al nuevo precio de costo[cite: 2].
4. **Si es un repuesto nuevo:** Crear el registro con nombre, código, categoría, compatibilidad, precio de costo y precio de venta[cite: 2].

### PASO 2: Actualización automática del stock
> **Actor Responsable:** SISTEMA[cite: 2]

1. Al guardar la entrada, el sistema suma automáticamente la cantidad al stock actual[cite: 2].
2. Si el repuesto tenía alerta de stock crítico, el sistema elimina la alerta si el nuevo stock supera el mínimo[cite: 2].
3. El movimiento queda registrado en el historial del repuesto con fecha, cantidad y usuario[cite: 2].

#### 🔀 Tabla de Decisión: ¿El stock del repuesto llegó al límite mínimo en algún momento?
| Condición | Acción Requerida |
| :--- | :--- |
| **Sí, hay alerta activa** | Al superar el mínimo con la nueva entrada, la alerta se cancela automáticamente[cite: 2]. |
| **No hay alerta** | El sistema registra la entrada normalmente sin acciones adicionales[cite: 2]. |

---

## Flujo 8 — Cierre de Caja y Resumen del Día
Proceso diario que el administrador o técnico encargado realiza al final de la jornada para verificar ingresos, órdenes y alertas pendientes[cite: 2].

### PASO 1: Revisión del Dashboard
> **Actores Responsables:** ADMINISTRADOR / RECEPCIONISTA[cite: 2]

1. Abrir el Dashboard gerencial en el sistema[cite: 2].
2. Revisar: órdenes creadas hoy, órdenes entregadas hoy y órdenes en proceso[cite: 2].
3. Verificar si hay equipos con más de 5 días sin movimiento (posible abandono o demora)[cite: 2].
4. Revisar alertas de stock crítico activas[cite: 2].

### PASO 2: Cuadre de caja
> **Actores Responsables:** ADMINISTRADOR / RECEPCIONISTA[cite: 2]

1. Ir al módulo de **Reportes $\rightarrow$ Ingresos del día**[cite: 2].
2. Verificar que el total del sistema coincida con el dinero físico en caja[cite: 2].
3. Registrar cualquier diferencia con su respectiva justificación[cite: 2].
4. Separar el efectivo del dinero de transferencias para el depósito bancario[cite: 2].

### PASO 3: Gestión de garantías próximas a vencer
> **Actores Responsables:** SISTEMA / ADMINISTRADOR[cite: 2]

1. El sistema muestra en el Dashboard las garantías que vencen en los próximos 15 días[cite: 2].
2. Revisar si alguno de esos clientes ha tenido problemas o regresos previos[cite: 2].
3. Opcionalmente, enviar un WhatsApp proactivo al cliente recordando que su garantía está próxima a vencer[cite: 2].

### PASO 4: Revisión de citas del día siguiente
> **Actores Responsables:** RECEPCIONISTA / SISTEMA[cite: 2]

1. Abrir la Agenda y revisar las citas programadas para el día siguiente[cite: 2].
2. Verificar que los materiales necesarios para visitas domiciliarias estén disponibles[cite: 2].
3. El sistema enviará recordatorios automáticos de citas a los clientes esa noche[cite: 2].
4. Asegurarse de que cada cita tenga un técnico asignado[cite: 2].

---
*SolarFix — Flujos de Atención — Fin del documento. Este documento debe actualizarse cada vez que se modifique un proceso en el sistema[cite: 2].*