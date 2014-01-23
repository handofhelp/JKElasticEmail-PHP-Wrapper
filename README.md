JKElasticEmail-PHP-Wrapper
==========================

Usage
-----
```
<?php
JKElasticEmail::SetApiKey(CFG_ELASTIC_API_KEY);
JKElasticEmail::SetUsername(CFG_ELASTIC_USERNAME);
JKElasticEmail::SetDefaultList('primary_list');

//...

JKElasticEmail::Subscribe('foobar@example.com');
```

Not too hard, maybe it shouldn't be static, but it works.
