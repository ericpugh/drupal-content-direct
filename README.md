Content Direct
============
Overview
------------
A Drupal 8 module that pushes content from one site to another using RESTful 
web services in Drupal 8 core. The module provides a "Content Direct" tab on Node, Taxonomy Term, and File entities and 
allows an individual entities to be pushed to a configured remote Drupal 8 site.

Installation
-----------
1. Place the drupal-content-direct directory in your modules directory of the "pushing" site.
2. Enable the content_direct module at admin/modules.
3. Configure Remote Sites at /admin/config/content_direct/remote_site
4. Perform content push on an entity's "Content Direct Actions" form. i.e. /node/1/content_direct

> **Important**: Content Direct expects the remote site to be configured identically to the authoring site, including
matching fields and configuration. In addition, the remote site's REST settings must also be configured to allow 
Content Direct to perform CRUD actions. Example rest.resource.*.yml config files been provided in /examples. Currently, 
Content Direct only supports Basic Auth with the HAL+JSON format, although other authentication methods and format
may be added.


