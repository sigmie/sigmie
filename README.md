<p align="center"><img src="https://res.cloudinary.com/markos-nikolaos-orfanos/image/upload/v1569788682/ni-logo_myntnw.png" width="400"></p>

<p align="center">
<a href="https://circleci.com/gh/nicoorfi/ni-elastic"><img src="https://circleci.com/gh/nicoorfi/ni-elastic.svg?style=svg&circle-token=ef57d3cd50af58d1f118f79805b5517a9d593fac" alt="Build Status"></a>

<a href="https://codecov.io/gh/nicoorfi/ni-elastic">
  <img src="https://codecov.io/gh/nicoorfi/ni-elastic/branch/master/graph/badge.svg" alt="Code coverage"/>
</a>


<a href="https://packagist.org/packages/nicoorfi/ni-elastic">
  <img src="https://img.shields.io/github/v/release/nicoorfi/ni-elastic?color=red&label=stable&logo=stable" alt="Stable version"/>
</a>


<a href="https://packagist.org/packages/nicoorfi/ni-elastic">
  <img src="https://img.shields.io/packagist/dt/nicoorfi/ni-elastic?color=green" alt="Latest Stable Version"/>
</a>

<a href="https://packagist.org/packages/nicoorfi/ni-elastic">
  <img src="https://img.shields.io/badge/License-MIT-blue.svg" alt="License"/>
</a>
</p>

## ni - Elasticsearch
Lorem ipsum is placeholder text commonly used in the graphic, print, and publishing industries for previewing layouts and visual mockups.

## Installation

Make sure you have Composer installed on your machine and execute:

```
 composer require nicoorfi/ni-elastic
```
Afterwards you must require the vendor/autoload.php file in your code and you are ready to code! 

Here is a first example to get an idea.
```
<?php

require 'vendor/autoload.php';

$params = [
  'index' => $index->getIdentifier()
];

$response = $this->elasticsearch->indices()->create($params);

return $this->handler->handle($response, new IndexCreate);
```

You can find the documentation on the [website](https://ni-elastic.com/docs).

Check out the [Getting Started](https://ni-elastic.com/docs/1.0/Getting-started) page for a quick overview.

## Contributing
 Thank you for considering contributing to the ni - Elastic library! The main purpose of this repository is making Elasticsearch faster and easier to use with PHP. I would be grateful to the community for contributing bugfixes and improvements. So feel free to open a pull request.

## Contact
 For any question regarding this project feel free to send an e-mail to nico_orfi@yahoo.com.

 # Thanks
 A big thanks to [Beasty](http://www.beasty.me) for his awesome [Space Icons Set](https://www.sketchappsources.com/free-source/1139-space-icons-sketch-freebie-resource.html).
 
## License
The **ni - Elastic** library is an open-source software licensed under the [MIT license](https://choosealicense.com/licenses/mit).