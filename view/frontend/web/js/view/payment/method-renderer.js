define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    rendererList.push({
        type: 'sbp_payment',
        component: 'SwissBitcoinPay_SbpPayment/js/view/payment/method-renderer/sbp_payment'
    });

    return Component.extend({});
});