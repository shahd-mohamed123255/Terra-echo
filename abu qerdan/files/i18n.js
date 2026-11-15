(() => {
  const storageKey = "fieldShiftLanguage";
  const translations = {
    "Dashboard": "لوحة المعلومات",
    "AI Agent": "المساعد الذكي",
    "Planning": "التخطيط",
    "Simulation": "المحاكاة",
    "Farm Simulation": "محاكاة المزرعة",
    "Field Shift AI home": "الرئيسية - فيلد شيفت للذكاء الاصطناعي",
    "Main": "القائمة الرئيسية",
    "Interface language": "لغة الواجهة",
    "From space to soil": "من الفضاء إلى التربة",
    "Make every field decision with evidence": "اتخذ قراراتك الزراعية استنادًا إلى البيانات",
    "Explore Egypt crop-season histories, build a plan, then simulate it with the same farm inputs.": "استكشف السجلات التاريخية للمحاصيل والمواسم في مصر، وضع خطة، ثم حاكِها باستخدام بيانات المزرعة نفسها.",
    "Dashboard · Local project data": "لوحة المعلومات · بيانات المشروع المحلية",
    "Farm context": "بيانات المزرعة",
    "The prefilled farm is illustrative. Edit these values before treating any analysis as relevant to your field.": "بيانات المزرعة المعروضة للتوضيح. حدّثها قبل اعتبار أي تحليل مناسبًا لأرضك.",
    "Farm area (ha)": "مساحة المزرعة (هكتار)",
    "Current crop": "المحصول الحالي",
    "Soil type": "نوع التربة",
    "Closest data zone": "أقرب منطقة بيانات",
    "Planning season": "موسم التخطيط",
    "Available irrigation (mm)": "المياه المتاحة للري (مم)",
    "Temperature scenario": "سيناريو الحرارة",
    "Rainfall scenario": "سيناريو الأمطار",
    "Strategic Objective": "الهدف الاستراتيجي",
    "All Strategies": "جميع الاستراتيجيات",
    "Balanced Baseline": "المرجع المتوازن",
    "Water-Saving −22%": "توفير مياه −22%",
    "Soil Booster & Legume Integration": "معزز للتربة وبقوليات",
    "Maximum Tonnage Target": "الإنتاجية القصوى",
    "Winter": "الشتاء",
    "Summer": "الصيف",
    "Clay": "طينية",
    "Loam": "طميية",
    "Silt": "غرينية",
    "Sandy": "رملية",
    "Update crop profiles": "تحديث خطط المحاصيل",
    "Generate Agricultural Plans": "توليد الخطط الزراعية",
    "Updating Agricultural Plans…": "جارٍ توليد وتحديث الخطط الزراعية…",
    "Open simulation": "فتح المحاكاة",
    "Planning · Historic crop-season records": "التخطيط · استراتيجيات زراعية متعددة",
    "Smart Agricultural Planning": "التخطيط الزراعي الذكي",
    "Compare crops for this zone": "قارن الخطط الزراعية المولدة لهذه المنطقة",
    "Loading the project crop-season dataset…": "جارٍ تحميل بيانات المحاصيل والمواسم…",
    "Reading local agricultural data…": "جارٍ قراءة البيانات الزراعية المحلية…",
    "No complete crop-season records are available for this zone and season.": "لا تتوفر سجلات مكتملة للمحاصيل والموسم في هذه المنطقة.",
    "Lower water history": "أقل استهلاكًا للمياه تاريخيًا",
    "{season} · {count} complete seasons": "{season} · {count} مواسم مكتملة",
    "Historical data profile · {zone}": "ملف بيانات تاريخي · {zone}",
    "Seasonal crop irrigation: {value} mm": "الري الموسمي للمحصول: {value} مم",
    "Mean temperature: {value} °C": "متوسط درجة الحرارة: {value} °م",
    "Observed NDVI: {value}": "مؤشر الغطاء النباتي المرصود: {value}",
    "Dataset soil pH {ph}; crop optimum field {min}–{max}": "درجة حموضة التربة في البيانات {ph}؛ النطاق الأمثل للمحصول {min}–{max}",
    "Soil pH requirement unavailable": "متطلب درجة حموضة التربة غير متاح",
    "Historical coverage: {start}–{end}": "التغطية التاريخية: {start}–{end}",
    "Selected crop": "المحصول المحدد",
    "Use this crop": "اختيار هذا المحصول",
    "Suitability Score": "درجة الملاءمة",
    "View Details": "عرض التفاصيل",
    "Compare Plans": "مقارنة الخطط",
    "Select This Plan": "اختيار هذه الخطة",
    "Selected Plan": "الخطة المحددة حاليًا",
    "Run Simulation": "تشغيل المحاكاة",
    "Side-by-Side Plan Comparison": "مقارنة الخطط الزراعية جنبًا إلى جنب",
    "Agricultural Suitability": "الملاءمة الزراعية",
    "Water Consumption": "استهلاك المياه",
    "Environmental Compatibility": "التوافق البيئي",
    "Estimated Production": "الإنتاجية التقديرية",
    "Primary Risks": "المخاطر الرئيسية",
    "Resource Requirements": "المتطلبات والموارد",
    "Plan Details": "تفاصيل الخطة الزراعية",
    "Close": "إغلاق",
    "Mode A – Individual Simulation": "النمط (أ) – محاكاة الخطة المحددة",
    "Mode B – Compare All Plans": "النمط (ب) – محاكاة ومقارنة جميع الخطط",
    "Simulate & Compare All Plans (Mode B)": "⚡ محاكاة ومقارنة جميع الخطط (النمط ب)",
    "Run Plan Simulation (Mode A)": "▷ تشغيل محاكاة الخطة (النمط أ)",
    "Loaded Plan": "الخطة المحملة",
    "Field Condition": "حالة الحقل الزراعي",
    "Growth Condition": "حالة النمو",
    "Water Status": "الوضع المائي",
    "Environmental Suitability": "الملاءمة البيئية",
    "Risk Indicators": "مؤشرات المخاطر",
    "Simulation Summary": "ملخص المحاكاة",
    "Simulation Analysis & Recommendations": "التحليل الزراعي والتوصيات",
    "Overall Performance": "الأداء العام للخطة",
    "Positive Factors": "العوامل الإيجابية",
    "Negative Factors": "العوامل السلبية والمحددات",
    "Potential Risks": "المخاطر الزراعية المحتملة",
    "Water Problems": "تحديات المياه والري",
    "Recommended Improvements": "التوصيات الزراعية المقترحة",
    "Why the simulated field looks this way": "تفسير المظهر البصري للحقل المحاكى",
    "Vibrant Canopy & Fertile Soil": "غطاء نباتي نضر وتربة خصبة",
    "Moderate Growth (Suboptimal Conditions)": "نمو متوسط (ظروف دون المثلى)",
    "Moisture & Heat Stress (Wilting)": "إجهاد رطوبي وحراري (ذبول)",
    "Severe Drought & Parched Soil": "جفاف حاد وتربة متشققة",
    "Which plan is most suitable for my conditions?": "أي خطة هي الأنسب لظروفي الحالية؟",
    "Why did Plan A perform better than Plan B?": "لماذا تفوقت الخطة أ على الخطة ب؟",
    "Why does this field look unhealthy?": "لماذا يبدو الحقل غير صحي أو ذابلاً؟",
    "Is the water supply sufficient?": "هل إمدادات المياه كافية لري المحصول؟",
    "What should I change to improve the selected plan?": "ما الذي يجب تعديله لتحسين الخطة المختارة؟",
    "What happens if I increase the available water?": "ماذا يحدث إذا قمت بزيادة المياه المتاحة؟",
    "Can you explain the simulation results?": "هل يمكنك شرح نتائج المحاكاة وكيف تم حسابها؟",
    "Plan A": "الخطة (أ)",
    "Plan B": "الخطة (ب)",
    "Plan C": "الخطة (ج)",
    "Plan D": "الخطة (د)",
    "{crop} · {season} selected. Continue to Simulation to adjust scenario assumptions.": "تم اختيار {crop} · {season}. انتقل إلى المحاكاة لتعديل افتراضات السيناريو.",
    "Historical seasonal averages for {zone}, {season} ({start}–{end}). Crop-season rows are grouped by harvest year; these are not forecasts.": "متوسطات موسمية تاريخية لمنطقة {zone}، موسم {season} ({start}–{end}). جُمعت السجلات حسب سنة الحصاد؛ وهذه ليست تنبؤات.",
    "No complete {season} historical profile for {crop} in {zone}. No substitute zone is used.": "لا يتوفر ملف تاريخي مكتمل لموسم {season} للمحصول {crop} في منطقة {zone}. لم تُستخدم منطقة بديلة.",
    "Abu Qerdan · Agricultural assistant": "أبو قردان · المساعد الزراعي الذكي",
    "Ask about the data and your plan": "اسأل عن الخطط والمحاكاة والمحاصيل",
    "Responses combine retrieved local dataset evidence with the existing Agent API. No language model is implied when the API reports fallback mode.": "تجمع الإجابات بين التحليل الذكي للبيانات المحلية ونماذج البخر-نتح وواجهة الوكيل. إجابات مستندة لأرقام وحسابات المشروع الفعلية.",
    "Local data ready": "البيانات المحلية جاهزة",
    "I can retrieve historical crop, season, irrigation, temperature, vegetation-index, and national-yield information from the files in this project. Results are screening evidence, not farm-specific prescriptions.": "مرحباً، أنا أبو قردان. أفهم بيانات المشروع الزراعية ومواسم مصر واحتياجات الري، وأحلل الخطط ونتائج المحاكاة. اسألني عن أفضل خطة، كفاية المياه، أو تفسير مظهر الحقل.",
    "Ask about crop seasons, water, temperature, or yield…": "اسأل عن الخطط، كفاية المياه، المحاكاة، أو تحسين الخطة…",
    "Message Abu Qerdan": "أرسل رسالة إلى أبو قردان",
    "Ask": "اسأل",
    "Wheat water history": "سجل ري القمح",
    "Compare seasons": "مقارنة الخطط",
    "Yield history": "تفسير المحاكاة",
    "Evidence boundary": "حدود الأدلة",
    "Historical observations and screened crop requirements are distinct from predictions. Yield values are national, not field-level. Optional LLM answers require server-side provider configuration.": "المشاهدات التاريخية ومتطلبات المحاصيل الاسترشادية ليست تنبؤات قطعية. قيم الإنتاج وطنية ومبنية على سلسلة FAOSTAT (2015–2024).",
    "Project data": "بيانات المشروع",
    "What this workspace contains": "محتويات مساحة العمل",
    "Crop-zone-month rows": "سجلات المحصول والمنطقة والشهر",
    "Complete seasonal profiles": "ملفات المواسم المكتملة",
    "National crop-year records": "سجلات المحصول والسنة الوطنية",
    "Crop types in monthly data": "أنواع المحاصيل في البيانات الشهرية",
    "1 crop": "محصول واحد",
    "{count} crops": "{count} محاصيل",
    "The 8-row sample weather CSV is explicitly illustrative and is not used as observed weather.": "ملف الطقس التجريبي المكوّن من 8 سجلات توضيحي فقط ولا يُستخدم كطقس مرصود.",
    "Carry this plan into the simulation": "انقل هذه الخطة إلى المحاكاة",
    "Open My Farm": "افتح مزرعتي والمحاكاة",
    "From project history to field scenario": "من سجل المشروع إلى سيناريو الحقل",
    "Illustrative workspace": "مساحة عمل توضيحية",
    "Edit plan": "تعديل الخطة",
    "Farm Name": "اسم المزرعة",
    "Illustrative sample farm": "مزرعة نموذجية توضيحية",
    "Location": "الموقع",
    "Total Area": "المساحة الإجمالية",
    "Assigned Crops": "المحاصيل المحددة",
    "Historical Data Zone": "منطقة البيانات التاريخية",
    "Loading…": "جارٍ التحميل…",
    "DATA BOUNDARY": "حدود البيانات",
    "Historical crop-zone records · no live field sensor": "سجلات تاريخية للمحاصيل والمناطق · لا توجد مستشعرات حقلية مباشرة",
    "Illustrative plot layout · not a satellite image": "مخطط توضيحي للقطع · ليس صورة قمر صناعي",
    "Historic data profile": "ملف بيانات تاريخي",
    "NDVI": "مؤشر الغطاء النباتي",
    "Irrigation": "الري",
    "Temperature": "درجة الحرارة",
    "ETc": "البخر-نتح للمحصول",
    "Select plot {plot}": "اختر القطعة {plot}",
    "Unassigned": "غير محددة",
    "No crop assigned": "لم يُحدّد محصول",
    "NDVI {value}": "مؤشر الغطاء النباتي {value}",
    "{value} ha illustrative share": "{value} هكتار (حصة توضيحية)",
    "Assign at least one crop with a complete historical profile and enter a positive farm area.": "حدّد محصولًا واحدًا على الأقل له ملف تاريخي مكتمل، وأدخل مساحة مزرعة موجبة.",
    "▷ RUN SIMULATION": "▷ تشغيل المحاكاة الفردية",
    "Build your experiment": "أنشئ تجربتك ومحاكاتك",
    "Configure Your Simulation": "إعداد المحاكاة الزراعية",
    "Crop Library": "مكتبة المحاصيل",
    "Choose a crop supported by complete local crop-season records; each plot represents an equal illustrative area share.": "اختر محصولًا مدعومًا بسجلات محلية مكتملة؛ تمثل كل قطعة حصة متساوية توضيحية من المساحة.",
    "Available Crops": "المحاصيل المتاحة",
    "Remove crop from {plot}": "إزالة المحصول من القطعة {plot}",
    "No complete crop profile for this zone and season.": "لا يتوفر ملف محصول مكتمل لهذه المنطقة والموسم.",
    "From inputs to insight": "من المدخلات إلى الرؤى",
    "Simulating your farm’s potential": "جارٍ محاكاة الخطط الزراعية وتقييم الحقل",
    "Cancel Simulation": "إلغاء المحاكاة",
    "Historical profile + explicit scenario adjustments": "ملف تاريخي + تعديلات سيناريو محددة",
    "Simulation results": "نتائج المحاكاة الزراعية",
    "Local calculation complete": "اكتمل الحساب الزراعي المحلي",
    "Water supply not compared": "لم تتم مقارنة إمدادات المياه",
    "No irrigation-availability value was entered, so supply balance is unavailable.": "لم تُدخل قيمة للمياه المتاحة للري، لذلك لا يتوفر حساب التوازن.",
    "Water availability below estimated net demand": "إمدادات المياه أقل من الطلب الصافي المقدّر",
    "Entered water exceeds estimated net demand": "كمية المياه المدخلة تتجاوز الطلب الصافي المقدّر",
    "Estimated net water gap: {value} m³.": "عجز المياه الصافي المقدّر: {value} م³.",
    "Estimated water remaining: {value} m³.": "المياه المتبقية المقدّرة: {value} م³.",
    "Crop water profile": "ملف مياه المحصول",
    "Historical NDVI · {count} seasons": "مؤشر الغطاء النباتي التاريخي · {count} مواسم",
    "Scenario ETc / effective rainfall": "البخر-نتح للسيناريو / الأمطار الفعالة",
    "Water balance · whole farm": "التوازن المائي · المزرعة كاملة",
    "Estimated net irrigation demand": "الطلب الصافي المقدّر للري",
    "Available irrigation": "الري المتاح",
    "Not provided": "غير مدخل",
    "Net balance": "صافي التوازن",
    "Not calculated": "لم يُحسب",
    "Not available": "غير متاح",
    "{crop}: {start}–{end} ({count} complete crop-seasons)": "{crop}: {start}–{end} ({count} مواسم محصول مكتملة)",
    "Scenario and evidence": "السيناريو والأدلة",
    "Temperature adjustment": "تعديل درجة الحرارة",
    "Rainfall adjustment": "تعديل الأمطار",
    "Soil field": "حقل التربة",
    "not used in water calculation": "غير مستخدم في حساب المياه",
    "Historical crop-season records": "سجلات تاريخية للمحاصيل والمواسم",
    "Season / records": "الموسم / السجلات",
    "No crop": "لا يوجد محصول",
    "No crop assigned": "لم يُحدّد محصول",
    "The selected plan": "الخطة المحددة",
    "Crop yield": "إنتاج المحصول",
    "My farm": "مزرعتي",
    "Egypt": "مصر",
    "Ask about your farm or simulation…": "اسأل عن مزرعتك أو الخطط أو المحاكاة…",
    "Field Shift AI · Farm Simulation": "فيلد شيفت للذكاء الاصطناعي · محاكاة المزرعة",
    "Field Shift AI · Terra Echo Farm Intelligence": "فيلد شيفت للذكاء الاصطناعي · ذكاء تيرا إيكو الزراعي",
  };

  const arabic = {
    "Berseem clover": "البرسيم",
    "Faba bean": "الفول البلدي",
    "Maize": "الذرة",
    "Sugar beet": "بنجر السكر",
    "Wheat": "القمح",
    "Potato": "البطاطس",
    "Cotton": "القطن",
    "Rice": "الأرز",
    "Barley": "الشعير",
    "Sunflower": "دوار الشمس",
    "Alfalfa": "البرسيم الحجازي",
    "Soybeans": "فول الصويا",
    "Sorghum": "الذرة الرفيعة",
    "winter": "الشتاء",
    "summer": "الصيف",
    "assiut": "أسيوط",
    "cairo": "القاهرة",
    "dakahleya": "الدقهلية",
    "fayoum": "الفيوم",
    "gharbia": "الغربية",
    "giza": "الجيزة",
    "kafr_el_sheikh": "كفر الشيخ",
    "minya": "المنيا",
    "monufia": "المنوفية",
    "delta_center": "وسط الدلتا",
    "delta_east": "شرق الدلتا",
    "delta_west": "غرب الدلتا",
    "luxor_aswan": "الأقصر وأسوان",
    "sohag_qena": "سوهاج وقنا",
    "clay": "طينية",
    "loam": "طميية",
    "silt": "غرينية",
    "sandy": "رملية",
  };

  const reverse = new Map(Object.entries(translations).map(([english, translated]) => [translated, english]));
  const reverseLabels = new Map(Object.entries(arabic).map(([english, translated]) => [translated, english]));
  const language = () => document.documentElement.lang === "ar" ? "ar" : "en";
  const format = (text, values = {}) => Object.entries(values).reduce(
    (result, [key, value]) => result.replaceAll(`{${key}}`, String(value)),
    text,
  );
  const t = (text, values) => format(language() === "ar" ? (translations[text] || text) : (reverse.get(text) || text), values);

  function localizeText(text) {
    const trimmed = text.trim();
    if (!trimmed) return text;
    const translated = language() === "ar" ? translations[trimmed] : reverse.get(trimmed) || reverseLabels.get(trimmed);
    if (!translated) return text;
    const start = text.indexOf(trimmed);
    return `${text.slice(0, start)}${translated}${text.slice(start + trimmed.length)}`;
  }

  function translatePage(root = document.body) {
    if (!root || typeof document.createTreeWalker !== "function") return;
    const filter = typeof NodeFilter !== "undefined" ? NodeFilter.SHOW_TEXT : 4;
    const walker = document.createTreeWalker(root, filter);
    let node;
    while ((node = walker.nextNode())) {
      const localized = localizeText(node.nodeValue);
      if (localized !== node.nodeValue) node.nodeValue = localized;
    }
    root.querySelectorAll?.("*").forEach(element => {
      ["placeholder", "aria-label", "title", "data-question"].forEach(attribute => {
        const value = element.getAttribute(attribute);
        if (!value) return;
        const translated = language() === "ar" ? translations[value] : reverse.get(value) || reverseLabels.get(value);
        if (translated && translated !== value) element.setAttribute(attribute, translated);
      });
    });
  }

  function setLanguage(nextLanguage) {
    const next = nextLanguage === "ar" ? "ar" : "en";
    document.documentElement.lang = next;
    document.documentElement.dir = next === "ar" ? "rtl" : "ltr";
    if (next === "ar") {
      document.title = document.body.classList.contains("simulation-page")
        ? "Field Shift AI · محاكاة المزرعة"
        : "Field Shift AI · لوحة المعلومات والتخطيط الذكي";
    } else {
      document.title = document.body.classList.contains("simulation-page")
        ? "Field Shift AI · Farm Simulation"
        : "Field Shift AI · Terra Echo Farm Intelligence";
    }
    document.body.classList.toggle("is-rtl", next === "ar");
    try {
      localStorage.setItem(storageKey, next);
    } catch (error) {
      console.error("Language preference could not be saved.", error);
    }
    document.querySelectorAll("[data-language]").forEach(button => {
      button.setAttribute("aria-pressed", String(button.dataset.language === next));
    });
    translatePage();
    window.dispatchEvent(new CustomEvent("fieldshift:language-changed", { detail: { language: next } }));
  }

  function localizedSeason(value) { return t(value === "summer" ? "Summer" : "Winter"); }
  function localizedZone(value) { return language() === "ar" ? (arabic[value] || value) : value; }
  function localizedSoil(value) { return language() === "ar" ? (arabic[value] || value) : value; }
  function localizedCrop(value) {
    if (language() !== "ar") return value;
    const english = value.replaceAll("_", " ").replace(/\b\w/g, letter => letter.toUpperCase());
    const matchingLabel = Object.keys(arabic).find(label => label.toLowerCase() === english.toLowerCase());
    return arabic[matchingLabel] || value.replaceAll("_", " ");
  }

  window.FieldShiftI18n = { t, language, setLanguage, translatePage, season: localizedSeason, zone: localizedZone, soil: localizedSoil, crop: localizedCrop };
  let savedLanguage = "en";
  try {
    savedLanguage = localStorage.getItem(storageKey) || "en";
  } catch (error) {
    console.error("Language preference could not be read.", error);
  }
  document.querySelectorAll("[data-language]").forEach(button => {
    button.addEventListener("click", () => setLanguage(button.dataset.language));
  });
  setLanguage(savedLanguage);
  const observer = new MutationObserver(records => records.forEach(record => {
    if (record.type === "characterData") {
      const localized = localizeText(record.target.nodeValue);
      if (localized !== record.target.nodeValue) record.target.nodeValue = localized;
      return;
    }
    record.addedNodes.forEach(node => {
      if (node.nodeType === Node.ELEMENT_NODE) translatePage(node);
      else if (node.nodeType === Node.TEXT_NODE) {
        const localized = localizeText(node.nodeValue);
        if (localized !== node.nodeValue) node.nodeValue = localized;
      }
    });
  }));
  observer.observe(document.body, { childList: true, subtree: true, characterData: true });
})();
