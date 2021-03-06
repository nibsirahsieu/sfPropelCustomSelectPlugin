# sfPropelCustomSelectPlugin #

The `sfPropelCustomSelectPlugin` is a symfony plugin that provides a capability to select arbitrary columns in propel,
without loosing the power of ORM. This plugin is intented for propel 1.4. If you are using propel 1.5 you can using ModelCriteria::select() and ModelCriteria::withColumn() together to achieve the same result (http://www.propelorm.org/ticket/1172).

## Installation ##
  * Install the plugin

        git clone git://github.com/nibsirahsieu/sfPropelCustomSelectPlugin.git

  * Activate the plugin in the `config/ProjectConfiguration.class.php`

        [php]
        class ProjectConfiguration extends sfProjectConfiguration
        {
          public function setup()
          {
            ...
            $this->enablePlugins('sfPropelCustomSelectPlugin');
            ...
          }
        }
  * Change the path of the symfony builder settings in the `config/propel.ini` file of your project:

        [ini]
        propel.builder.peer.class = plugins.sfPropelCustomSelectPlugin.lib.builder.om.PHP5CustomPeerBuilder
        propel.builder.object.class = plugins.sfPropelCustomSelectPlugin.lib.builder.om.PHP5CustomObjectBuilder

  * (Re)build the model:
    ./symfony propel:build-model

## How to use ##
    [url]
    http://nibsirahsieu.wordpress.com/2010/11/14/sfpropelcustomselectplugin-sample-usage/
