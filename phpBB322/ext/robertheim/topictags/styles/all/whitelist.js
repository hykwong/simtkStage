$(function(){
	// Match all class starting with "rh_topictags_whitelist".
	$('[class^=rh_topictags_whitelist]').find('a').click(function(event){
		event.preventDefault();

		var $scope = angular.element($("#rhTopicTagsInputAppScope")).scope();
		var t = $(this).parent().text();
		$scope.$apply(function($scope) {
			$scope.addTag(t);
		});
	});
});
