#  Shopware document storage system

This project is a document storage system for Shopware 6. It allows you to store documents inside storage and query them via key or via different criteria. The goal of this system is to provide a read-optimized system for better scalability and performance and support different kinds of document storage engines like opensearch, mongodb, etc.

Feel free to contribute to this project or implement new storages. All implementations of storage should be tested in the same way, to ensure the same behavior independent of the chosen engine.

When implementing a new storage, please also make sure the storage can be setup locally.

The project is still under development and is not suitable for productive systems. As soon as we can ensure a stable version, this repository will be moved to the Shopware organization. This repository is then split into a mono repository and the different engines are split into many repositories. So it is possible to only need the required engine dependencies for each project