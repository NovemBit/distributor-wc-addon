## Description

Distributor WC add-on is for extending the Distributor plug-in (see [DistributorPlugin.com](https://distributorplugin.com))
functionality to handle intricacies of product distribution.
Currently it's handling product variations distribution.

## Requirements

- PHP 5.6+
- [WordPress](http://wordpress.org) 4.7+
- [Distributor](https://github.com/NovemBit/distributor) plug-in
- [Woocommerce](https://woocommerce.com) 3.0 +
- You need to have all attributes that will be used for creating variations in both sides (`Source` and `Destinations`)

## Install
Just install and activate it as simple WordPress plugin. You can get stable version from [here] or just clone repository's master branch.

## Plugin Usage
After you've installed and activated plugin, on every variation action (create | update | delete) it will perform those actions in all destiantions where parent post was pushed. So, you don't need to perform additional actions, just install, activate and enjoy
