Plugin de gestion de l'import/export Pimcore.
==========================================

Composer
--------
Package name (require): socoda-pimcore/plugin-import-export5 

Static
------
```bash
bin/console assets:install web --symlink --relative
```

Configuration
-------------

`var/plugins/PluginImportExport/config/configProcessor.xml`
Ce fichier est généré à l'installation du plugin.

Exemple de déclaration de processors :

```xml
<?xml version="1.0"?>
<zend-config xmlns:zf="http://framework.zend.com/xml/zend-config-xml/1.0/">
    <importers>
        <importer>
            <type>products</type>
            <class>Website\Importer\Product</class>
            <update authorized="*"/>
        </importer>
    </importers>
    <exporters>
        <exporter>
            <type>assets</type>
            <class>Website\Exporter\Asset</class>
            <export-sub-folder>product/images</export-sub-folder>
        </exporter>
    </exporters>
</zend-config>
```
**export-sub-folder** : Sous dossier destination de l'exporter. 
Le dossier de base est défini dans le config config.xml (exportFolder).


Mise à jour des champs lors des imports
-----

Modifier le fichier de configuration du plugin : 
var/plugins/PluginImportExport/config/configProcessor.xml

```xml
<config>
    ...
    <importers>
        <importer>
            <type>categories</type>
            <class>Website\Importer\Category</class>
            <update authorized="*"/>
        </importer>
        <importer>
            <type>products</type>
            <class>Website\Importer\Product</class>
            <update authorized="*"/>
        </importer>
        ...
    </importers>
</config>
```

### Exemple de configuration
Les valeurs définies dans l'attribut **protected** et **authorized** sont les champs du fichier csv d'import, séparés par une virgule.

**Interdire la mise à jour (aucun champ mis à jour) :**
```xml
 <update protected="*"></update>
```

**Autoriser à mettre à jour tous les champs :**
```xml
 <update authorized="*"></update>
```

**Autoriser seulement la mise à jour des champs prix et quantité :**
```xml
 <update authorized="prix, quantite"></update>
```

**Autoriser tous les champs sauf le prix :**
```xml
 <update protected="prix"></update>
```

Configuration global
----
`var/plugins/PluginImportExport/config/config.xml`

```xml
<?xml version="1.0"?>
<response>
    <report>
        <emails>
            <email>dest@email.fr</email>
        </emails>
    </report>
    <lastImportDate/>
    <exportFolder>/home/web-user/htdocs/export/lgx/gi</exportFolder>
    <importFolder>/home/web-user/htdocs/import/lgx/gi</importFolder>
    <lastExportDate>2018-07-20 14:52:51</lastExportDate>
</response>
```
**report/emails** : Les emails sont paramétrable dans le backoffice de Pimcore et sont sauvegardés dans ce fichier.
**exportFolder** : Dossier destination racine des fichiers d'export.
**importFolder** : Dossier des fichiers d'import.
**lastImportDate** : Date du dernier import global. Alimenté automatiquement lors des imports.
**lastExportDate** : Date du dernier export global. Alimenté automatiquement lors des exports.


Lignes de commande
------------------

###Imports

```console
php bin/console galilee:import -t {type}

# exemples :
php bin/console galilee:import # Excécute tous les imports
php bin/console galilee:import -t categories
php bin/console galilee:import -t customers
php bin/console galilee:import -t products
php bin/console galilee:import -t prices

```

####Arguments :

* -t : import type. (voir website/var/plugins/PluginImportExport/config/configProcessor.xml)

```$xml
<type>family</type>
```

Les assets doivent être dans un fichier zip portant le nom du type d'import :
```
categroies.csv
categories.zip
products.csv
products.zip
```

ou placés dans un dossier **assets** au même niveau que le fichier csv.
```
categroies.csv
products.csv
assets
   img1.jpg
   img2.jpg
```

**Le zip est prioritaire sur le dossier assets**

###Exports

```console
php bin/console galilee:export -t {type} -d {YYYY-MM-JJ HH:MM:SS}
php bin/console galilee:export -t prices
php bin/console galilee:export -t products
php bin/console galilee:export -t assets

# Tout exporter :
php bin/console galilee:export
```

####Arguments :

* -t : import type. (voir var/plugins/PluginImportExport/config/configProcessor.xml)
* -d : Date d'export. Exporter les élements modifiés depuis cette date.

L'emplacement des fichiers d'export est défini dans var/plugins/PluginImportExport/config/config.xml

```$xml
<exportFolder>/home/web-user/htdocs/export/lgx/gi</exportFolder>
```

Implémentation
--------------
**Importer**
```php
class Product extends \PluginImportExport\Processor\Importer\AbstractImporter
```

**Exporter**
```php
class Asset extends \PluginImportExport\Processor\Exporter\AbstractExporter
```

Rapport d'import
----------------

**Ajouter un destinataire**

Outils > Import/Export > Rapports d'import > Ajouter un destinataire


**Visualiser les rapports**

Outils > Journaux de l'application


**Visualiser les emails envoyés**

Outils > Courriel > E-mails envoyés (Global)
