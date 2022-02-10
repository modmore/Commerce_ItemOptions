ItemOptions for Commerce
------------------------

With the Item Options module, you can make simple configurable products. It's useful for a wide range of use cases,
such as ordering food/drinks, configuring bespoke products, upselling additional insurance, etc.

For example, you may have a base "veggieburger menu" product, and allow customers to add different sides or drinks
to each item, optionally adjusting the price of the menu product to match their selection.


The module works by looking for whitelisted keys when adding a product to the cart. If those keys contain a valid
product ID, the product is added as an order item adjustment, allowing it to affect the price. The adjustments can be
accessed in both the front-end and back-end.
