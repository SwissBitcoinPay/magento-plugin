define([
    'Magento_Checkout/js/view/payment/default',
    'mage/url',
    'jquery',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/model/error-processor',
    'Magento_Checkout/js/model/full-screen-loader'
], function (Component, url, $, quote, customerData, errorProcessor, fullScreenLoader) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'SwissBitcoinPay_SbpPayment/payment/sbp_payment'
        },

        initialize: function () {
            this._super();
            return this;
        },

        getCode: function() {
            return 'sbp_payment';
        },

        getTitle: function() {
            return 'Swiss Bitcoin Pay';
        },

        placeOrder: function () {
            var self = this;
            fullScreenLoader.startLoader();

            var orderData = {
                form_key: $.cookie('form_key'),
                quote_id: quote.getQuoteId(),
                amount: quote.getTotals()()['grand_total'],
                currency: quote.getTotals()()['quote_currency_code']
            };

            $.ajax({
                url: url.build('sbppayment/payment/redirect'),
                type: 'POST',
                dataType: 'json',
                data: orderData,
                success: function (response) {
                    if (response.success && response.redirect_url) {
                        window.location.href = response.redirect_url;
                    } else {
                        errorProcessor.process(response);
                    }
                },
                error: function (response) {
                    errorProcessor.process(response);
                },
                complete: function () {
                    fullScreenLoader.stopLoader();
                }
            });

            return false;
        }
    });
});