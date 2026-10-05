# 💀 Calavera IA

Una aplicación web temática e interactiva basada en la cultura del **Día de Muertos** y **La Catrina**, alimentada por modelos de inteligencia artificial a través de **OpenRouter** y un backend ligero en **PHP** con técnica **RAG** (Retrieval-Augmented Generation).

---

## 🚀 Características

- 🎭 **Modos de Respuesta**:
  - **Modo Verso (Calavera Literaria)**: La Catrina responde en cuartetas rimadas (octosílabos).
  - **Modo Prosa**: Respuestas detalladas, solemnes y poéticas sobre cultura e historia.
- 📚 **RAG Integrado**: Búsqueda en base de conocimientos (`data/knowledge.json`) para responder datos precisos sobre el Día de Muertos y Mictlán.
- 🛡️ **Filtro de Moderación**: Sistema de filtrado en PHP para mantener interacción segura.
- ⚡ **Backend Ligero**: Desarrollado en PHP nativo (compatible con Hostinger y hosting compartido).

---

## 📁 Estructura del Proyecto

```text
├── backend/
│   ├── api.php           # Endpoint principal (comunicación con OpenRouter)
│   ├── moderation.php    # Filtros de seguridad y moderación
│   └── rag.php           # Motor de búsqueda contextual RAG
├── data/
│   └── knowledge.json    # Base de conocimientos temática
├── images/               # Recursos gráficos
├── index.html            # Interfaz principal del Chat
├── historia.html         # Sección educativa sobre el Día de Muertos
├── cultura.html          # Elementos culturales y de ofrenda
├── chat.js               # Lógica e interacción del cliente
├── styles.css            # Estilos y animaciones
├── .htaccess             # Seguridad y optimización Apache
└── .env.example          # Plantilla de variables de entorno
```

---

## 🛠️ Instalación y Configuración

1. **Clonar el repositorio:**
   ```bash
   git clone https://github.com/tu-usuario/calavera-ia.git
   cd calavera-ia
   ```

2. **Configurar las variables de entorno:**
   - Copia el archivo `.env.example` y renómbralo a `.env`:
     ```bash
     cp .env.example .env
     ```
   - Abre el archivo `.env` e introduce tu **API Key** de OpenRouter:
     ```env
     API_KEY=tu_api_key_de_openrouter
     MODELO=google/gemma-4-31b-it:free
     ```

3. **Despliegue / Servidor local:**
   - Sube los archivos a tu servidor web con soporte PHP (ejemplo: Hostinger, Apache, Nginx).
   - Asegúrate de que el archivo `.env` esté protejido por el `.htaccess` o fuera del directorio público `public_html`.
