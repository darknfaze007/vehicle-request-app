angular.element(document).ready(function() {
    angular.module('vrmApp', []);
    angular.bootstrap(document, ['vrmApp']);
});
'use strict';

// Declare app level module which depends on filters, and services
angular.module('vrmApp', []);

/* Controllers */
function newController($scope) {
    $scope.calculate = function() {
        $scope.total_fee = Math.round(($scope.no_days * $scope.day_rate) * 100) / 100;
    }
}
