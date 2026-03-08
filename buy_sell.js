document.addEventListener("DOMContentLoaded", () => {
    const orderType = document.getElementById("order_type");
    const coin = document.getElementById("coin");
    const amount = document.getElementById("amount");
    const price = document.getElementById("price");
    const total = document.getElementById("totalDisplay");
  
    async function fetchPrice() {
      const symbol = coin.value + "USDT";
      try {
        const res = await fetch(`https://api.binance.com/api/v3/ticker/price?symbol=${symbol}`);
        const data = await res.json();
        return parseFloat(data.price);
      } catch (err) {
        console.error("Error fetching price:", err);
        return 0;
      }
    }
  
    async function updateForm() {
      const type = orderType.value;
      const amt = parseFloat(amount.value) || 0;
  
      if (type === "market") {
        const live = await fetchPrice();
        price.value = live.toFixed(2);
        price.readOnly = true;
        total.textContent = "$" + (live * amt).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
      } else {
        price.readOnly = false;
        const limit = parseFloat(price.value) || 0;
        total.textContent = "$" + (limit * amt).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
      }
    }
  
    orderType.addEventListener("change", updateForm);
    coin.addEventListener("change", updateForm);
    amount.addEventListener("input", updateForm);
    price.addEventListener("input", updateForm);
  
    updateForm();
  });
  