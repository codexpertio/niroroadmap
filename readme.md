**NiroRoadmap** 🚀
==================

**A powerful WordPress plugin to create and manage product roadmaps with a Kanban-style interface.**

![image](https://github.com/user-attachments/assets/3f7a882a-794b-4ad8-830f-037dbfb91e4a)

**📌 Features**
---------------

-   **Custom Post Type (`task`)** for roadmap items.
-   **Custom Taxonomies (`niroroadmap_status`, `niroroadmap_product`)** for categorization.
-   **Drag-and-Drop Sorting** for tasks and roadmap stages.
-   **REST API Support** to interact with roadmap data.
-   **Kanban Board UI** for a visual workflow.
-   **Upvote & Downvote System** to prioritize tasks.
-   **Shortcode Support** for embedding roadmaps on pages and posts.
-   **Admin Panel Integration** for managing tasks and settings.

* * * * *

**📦 Installation**
-------------------

### **From WordPress Plugin Upload:**

1.  Download the latest `.zip` file from [Releases](https://github.com/easycommercedev/niroroadmap/releases).
2.  Go to **WordPress Dashboard > Plugins > Add New**.
3.  Click **Upload Plugin**, select the `.zip` file, and click **Install Now**.
4.  Click **Activate Plugin**.

### **From GitHub (Manual Installation):**

1.  Clone the repository:

    ```
    git clone https://github.com/easycommercedev/niroroadmap.git
    composer update --no-dev

    ```

2.  Upload the `niroroadmap` folder to `wp-content/plugins/`.
3.  Activate the plugin via **WordPress Dashboard > Plugins**.

* * * * *

**🛠️ How to Use**
------------------

### **1️⃣ Add Roadmap Tasks**

-   Navigate to **NiroRoadmap** in the WordPress admin menu.
-   Click **"Add New"** to create a roadmap task.
-   Assign a **stage** (e.g., "In Progress", "Completed") and a **Product** (if applicable).

### **2️⃣ Display the Roadmap (Shortcode)**

-   Add the following shortcode to a **page or post** to display the roadmap:

    ```
    [roadmap]

    ```

-   To filter by product:

    ```
    [roadmap product="Product_ID"]

    ```

-   This will generate a **Kanban-style board** with draggable tasks.

### **3️⃣ Drag-and-Drop Sorting (Admin & Frontend)**

-   **Reorder Stages in Admin:** The roadmap stages (`niroroadmap_status`) can be sorted via drag-and-drop in the WordPress admin taxonomy list.
-   **Drag-and-Drop Tasks in Kanban:** You can **drag tasks between columns** to update their status dynamically.

* * * * *

**🖥️ REST API Endpoints**
--------------------------

### **📌 Task Endpoints**

-   **Move a task to a different stage:**

    ```
    POST /wp-json/niroroadmap/v1/tasks/{id}/move

    ```

    **Params:** `id` (task ID), `stage` (new stage ID)

-   **Get a task's details:**

    ```
    GET /wp-json/niroroadmap/v1/tasks/{id}

    ```

-   **Vote on a task (Upvote or Downvote):**

    ```
    POST /wp-json/niroroadmap/v1/tasks/{id}/vote

    ```

    **Params:** `id` (task ID), `type` (`upvote` or `downvote`)

-   **Sort tasks within a stage:**

    ```
    POST /wp-json/niroroadmap/v1/tasks/order

    ```

    **Params:** `order` (Array of task IDs in new order)

### **📌 Stage Endpoints**

-   **Sort roadmap stages:**

    ```
    POST /wp-json/niroroadmap/v1/stages/order

    ```

    **Params:** `order` (Array of stage IDs in new order)

### **📌 Option Endpoints**

-   **Get a WordPress option:**

    ```
    GET /wp-json/niroroadmap/v1/option?key=option_name

    ```

-   **Update an option:**

    ```
    POST /wp-json/niroroadmap/v1/option

    ```

    **Params:** `key` (option name), `value` (new value)

-   **Delete an option:**

    ```
    DELETE /wp-json/niroroadmap/v1/option?key=option_name

    ```

* * * * *

**🛠️ Contributing**
--------------------

Contributions are welcome! If you find a bug or have a feature request, feel free to **open an issue** or submit a **pull request**.

### **Development Setup:**

1.  Clone the repository:

    ```
    git clone https://github.com/easycommercedev/niroroadmap.git

    ```

2.  Install dependencies:

    ```
    composer install

    ```

3.  Make your changes and submit a **pull request**!

* * * * *

**📜 License**
--------------

NiroRoadmap is licensed under the **GNU General Public License v3.0**.

* * * * *

**📧 Support**
--------------

Need help? Contact us at **<hi@easycommerce.dev>** or open an issue on GitHub.

* * * * *

### **💡 Built with ❤️ by [EasyCommerce](https://easycommerce.dev/)**

🔗 **Website:** [https://easycommerce.dev](https://easycommerce.dev/)\
🔗 **GitHub:** <https://github.com/easycommercedev/niroroadmap>
