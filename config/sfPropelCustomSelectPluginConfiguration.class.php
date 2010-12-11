<?php

class sfPropelCustomSelectPluginConfiguration extends sfPluginConfiguration
{
  public function initialize()
  {
    if (false === strpos(Propel::VERSION, '1.4'))
    {
      throw new sfException('This plugin is intended for propel 1.4');
    }
  }
}
