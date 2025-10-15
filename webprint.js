function dataPrint() {
	// 印刷部分を表示する
	let data_element = document.getElementById('data_print');
	data_element.className = "";
	
	window.print();
}
