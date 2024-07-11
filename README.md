Collabora Online connector for Drupal
=====================================

This module integrate Collabora Online to Drupal. You can use it to
view and edit documents from within Drupal.

Requirements:

- Collabora Online server installed and running.
- Drupal 10 (tested on 10.1), maybe compatible with 9.
- JWT and Media are set as dependencies for the module and are
  necessary.

Installation
------------

See the [Drupal guide to install
modules](https://www.drupal.org/docs/extending-drupal/installing-modules).

To install on a project, user PHP composer:

```shell
composer require drupal/collabora_online
```

If you get an error saying `but it does not match your
minimum-stability`, you may need to adjust the `minimum-stability`
field in the `composer.json` of your project.

Then you can go into Drupal logged as an admin and go to _Extend_. In
the list you should be able to find _Collabora Online_ and enable it.

From there you can access the module specific configuration.

### Development installation

If you intend to develop or contribute to the module, you can install
directly from git.

In your Drupal setup, in the directory `modules/contrib` extract the
module into a directory `collabora_online`.

You can get it directly with git:

```sh
git clone https://github.com/CollaboraOnline/collabora-drupal.git collabora_online
```

Configuration
-------------

There are few step necessary in Drupal to configure the integration.

Log into Drupal as an admin.

### JWT key

Go to _Configuration_ > _System_ > _Keys_

- Create a _JWT HMAC_ key, with the _HS256_ algorithm.
- Set it to be provided by the configuration.

You can create a secret using the following shell command:

```shell
head -c 64 /dev/urandom | base64 -w 0
```

### Collabora Online

Go to _Configuration_ > _Media_ > _Collabora Online Settings_

- _Collabora Online server URL_: the URL of the collabora online
  server. Note that you have to take into considerartion containers. If
  you run Drupal in one container and Collabora Online in another, you
  can not use `localhost`.
- _WOPI host base URL_: how the Collabora Online server can reach the
  Drupal server. Usually it is the public URL of this Drupal server.
- _JWT Private Key ID_: the id of the key created above.

Optional

- _Disable TLS certificate check for COOL_: If you configure a
  development server you might have self-signed certificate. Checking
  this is **INSECURE** but allow the drupal server to contact the
  collabora online server is the certificate doesn't check.
- _Access Token Expiration_: In second the expiration of the token to
  access the document. Default to 86400 seconds (24 hours).

### COOL

Some configuration changes might be necessary on the Collabora Online
side.

CSP for embedding must set properly to embed the Collabora Online
frame.

### Fields

To be able to attach document to Drupal content nodes, you need to
create a field.

Login as an admin, and got to the admnistration section.

- Go to _Structure_ > _Content types_
- Find the appropriate content and click _Manage fields_.
- Click _+ Create a new field_ (or if you already have created one you
  can re-use one)
- Enter a label, select _Media_, click _Continue_
- At the bottom for _Media type_, select _Document_. Click _Save
  Settings_. _Type of item to reference_ should have been set to the
  default value of _Media_.

You also must set the viewer for this kind of media.

- Go to _Structure_ > _Media Types_
- Select _Manage Display_ for _Document_
- In the _Field_ section, select in the _Format_ column, choose
  _Collabora Online Preview_.
- Click _Save_.

### User permissions

There are three levels of permissions. _Administrator_,
_Collaborator_, and _Viewer_, in order of most privileges to the
least. Each lesser privilege is included in the higher level.
_Administrator_ includes _Collaborator_, and _Collaborator_ includes
_Viewer_.

By default, the user role `administrator` is mapped to the Collabora
Online administrator. This allow accessing the console.

The user role `authenticated` is the default permission for
collaboration, i.e. edit a document with Collabora Online.

The user role `anonymous` by default disallows editing and is the
minimal permission for viewing documents in Collabora Online.

### Other configuration

If you need to change the accepted extensions to upload, go to
_Administration_ > _Structure_ > _Media Type_, for the line
_Documents_, click _Edit_, then click _Manage Fields_, and for the
right field, _Edit_:

- You can change the allowed file extensions.

To increase the maximum upload size (it is indicated on that page),
you need to increase the value in the PHP configuration.

Usually you can add a file `max_file_size.ini` (the name isn't much
important except its extension should be `.ini`) into
`/etc/php/conf.d/` (the path may be different) and put the following:

```
post_max_size = 30M
upload_max_filesize = 30M
```

These set the limits to a maximum of 30M. You can change as appropriate.

License
-------

This module is published under the MPL-2.0 license.
