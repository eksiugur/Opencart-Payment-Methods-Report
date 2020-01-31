<?php
class ControllerExtensionReportSalePayment extends Controller {
	public function index() {
		$this->load->language('extension/report/sale_payment');

		$this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('report_sale_payment', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=report', true));
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }


        $data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=report', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/report/sale_payment', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/report/sale_payment', 'user_token=' . $this->session->data['user_token'], true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/report/sale_payment_form', $data));
	}

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/report/sale_payment')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    public function report(){
        $this->load->language('extension/report/sale_payment');

        if (isset($this->request->get['filter_date_start'])) {
            $filter_date_start = $this->request->get['filter_date_start'];
        } else {
            $filter_date_start = '';
        }

        if (isset($this->request->get['filter_date_end'])) {
            $filter_date_end = $this->request->get['filter_date_end'];
        } else {
            $filter_date_end = '';
        }
        if (isset($this->request->get['filter_store_id'])) {
            $filter_store_id = $this->request->get['filter_store_id'];
        } else {
            $filter_store_id = '';
        }

        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        $AllStore = [];
        $AllStore[] = ['name' => $this->config->get('config_name'),'id' => 0];

        $this->load->model('setting/store');
        $stores = $this->model_setting_store->getStores();

        foreach ($stores as $store) {
            $AllStore[] = array(
                'id' => $store['store_id'],
                'name'     => $store['name']
            );
        }
        $data['filter_stores'] = $AllStore;
        $data['methods'] = [];
        if(is_numeric($filter_store_id)){
            $this->load->model('extension/report/sale');
            $files = glob(DIR_APPLICATION . 'controller/extension/payment/*.php');
            if ($files) {
                foreach ($files as $file) {
                    $PaymentCode = basename($file, '.php');
                    $PaymentStatus = $this->config->get('payment_' . $PaymentCode . '_status');
                    if(isset($PaymentStatus)){
                        $Where = '';
                        if(!empty($filter_date_start)){
                            $Where .= " AND date_added >= '".$filter_date_start."'";
                        }
                        if(!empty($filter_date_end)){
                            $Where .= " AND date_added <= '".$filter_date_end."'";
                        }
                        $Select = "SUM(case when payment_code = '{$PaymentCode}' then 1 else 0 end) AS Total";
                        $QueryBuilder = "SELECT {$Select} FROM ".DB_PREFIX."order WHERE store_id = {$filter_store_id} {$Where}";
                        $OrderTotal = $this->db->query($QueryBuilder);
                        $this->load->language('extension/payment/' . $PaymentCode, 'extension');
                        $data['methods'][$PaymentCode] = array(
                            'name'       => $this->language->get('extension')->get('heading_title'),
                            'total' => $OrderTotal->row['Total'],
                        );
                    }
                }
            }
        }

        $TotalRecord = count($data['methods']);
        $data['user_token'] = $this->session->data['user_token'];
        $url = '';
        if (isset($this->request->get['filter_date_start'])) {
            $url .= '&filter_date_start=' . $this->request->get['filter_date_start'];
        }
        if (isset($this->request->get['filter_date_end'])) {
            $url .= '&filter_date_end=' . $this->request->get['filter_date_end'];
        }
        if (isset($this->request->get['filter_store_id'])) {
            $url .= '&filter_store_id=' . $this->request->get['filter_store_id'];
        }
        $pagination = new Pagination();
        $pagination->total = $TotalRecord;
        $pagination->page = $page;
        $pagination->limit = $this->config->get('config_limit_admin');
        $pagination->url = $this->url->link('report/report', 'user_token=' . $this->session->data['user_token'] . '&code=sale_payment' . $url . '&page={page}', true);

        $data['pagination'] = $pagination->render();

        $data['results'] = sprintf($this->language->get('text_pagination'), ($TotalRecord) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($TotalRecord - $this->config->get('config_limit_admin'))) ? $TotalRecord : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $TotalRecord, ceil($TotalRecord / $this->config->get('config_limit_admin')));

        $data['filter_date_start'] = $filter_date_start;
        $data['filter_date_end'] = $filter_date_end;
        $data['filter_store_id'] = $filter_store_id;

        return $this->load->view('extension/report/sale_payment_info', $data);
    }
}
