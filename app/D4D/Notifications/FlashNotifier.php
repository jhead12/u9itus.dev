<?php namespace D4D\Notifications;

use Illuminate\Session\Store;

class FlashNotifier {

		private $session;

		function _construct(Store $session)
		{
			$this->session = $session;
		}

		public function success($message)
		{

			$this->message($message, 'success');

		}
		public function message($message, $level = 'info')
		{

			$this->session->flash('flash_notification.message', $message);
			$this->session->flash('flash_notification.level', $level);

		}
		

}