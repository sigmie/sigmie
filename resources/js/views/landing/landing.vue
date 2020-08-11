<template>
  <div class="leading-normal tracking-normal text-white gradient">
    <vue-headful title="Sigmie Application | Awesome search on your Infastructure" />

    <nav class="fixed w-full bg-white shadow z-30 top-0 text-white py-1">
      <div class="w-full px-5 container mx-auto flex justify-between items-center mt-0 py-2">
        <div class>
          <div
            class="toggleColour text-white no-underline hover:no-underline font-bold text-2xl lg:text-4xl"
          >
            <logo-default class="lg:pl-24 h-16"></logo-default>
          </div>
        </div>

        <div>
          <inertia-link
            :href="$route('register')"
            class="items-center px-4 py-2 border border-transparent text-sm leading-5 font-semibold rounded-md text-cool-gray-800 tracking-wide bg-white hover:bg-cool-gray-50 focus:outline-none focus:border-cool-gray-700 active:bg-cool-gray-100 transition duration-150 ease-in-out"
          >Register</inertia-link>

          <inertia-link
            :href="$route('login')"
            class="items-center px-4 py-2 border border-transparent text-sm leading-5 font-semibold rounded-md text-cool-gray-800 tracking-wide bg-white hover:bg-cool-gray-50 focus:outline-none focus:border-cool-gray-700 active:bg-cool-gray-100 transition duration-150 ease-in-out"
          >Login</inertia-link>
        </div>
      </div>
    </nav>

    <div class="pt-32 pb-10">
      <div class="container px-3 mx-auto flex flex-wrap flex-col md:flex-row items-center lg:px-24">
        <div
          class="flex flex-col order-2 md:order-1 md:w-3/5 justify-center items-start text-center md:text-left md:pr-10 lg:pr-20 pb-4"
        >
          <h1
            class="mt-9 font-display tracking-wide text-4xl leading-none font-semibold text-white sm:text-5xl lg:text-4xl pb-2"
          >Your Elasticsearch backend.</h1>
          <p
            class="leading-normal text-xl mb-0 md:mb-3 opacity-75"
          >Sigmie is an application of which helps you create your Elasticsearch cluster, optimize your search results, and simplifies common tasks.</p>

          <inertia-link
            :href="$route('register')"
            class="mx-auto lg:mx-0 bg-white hover:bg-cool-gray-50 text-gray-600 font-semibold rounded-lg my-6 py-3 px-8 shadow cursor-pointer transition-shadow"
          >Get early access</inertia-link>
        </div>

        <div class="w-full order-1 md:order-2 py-6 md:w-2/5 text-center">
          <illustration-hero class="m-auto max-w-xs z-50"></illustration-hero>
        </div>
      </div>
    </div>

    <div class="relative -mt-12 lg:-mt-24">
      <svg viewBox="0 0 1428 174">
        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
          <g transform="translate(-2.000000, 44.000000)" fill="#FFFFFF" fill-rule="nonzero">
            <path
              d="M0,0 C90.7283404,0.927527913 147.912752,27.187927 291.910178,59.9119003 C387.908462,81.7278826 543.605069,89.334785 759,82.7326078 C469.336065,156.254352 216.336065,153.6679 0,74.9732496"
              opacity="0.100000001"
            />
            <path
              d="M100,104.708498 C277.413333,72.2345949 426.147877,52.5246657 546.203633,45.5787101 C666.259389,38.6327546 810.524845,41.7979068 979,55.0741668 C931.069965,56.122511 810.303266,74.8455141 616.699903,111.243176 C423.096539,147.640838 250.863238,145.462612 100,104.708498 Z"
              opacity="0.100000001"
            />
            <path
              d="M1046,51.6521276 C1130.83045,29.328812 1279.08318,17.607883 1439,40.1656806 L1439,120 C1271.17211,77.9435312 1140.17211,55.1609071 1046,51.6521276 Z"
              opacity="0.200000003"
            />
          </g>
          <g transform="translate(-4.000000, 76.000000)" fill="#FFFFFF" fill-rule="nonzero">
            <path
              d="M0.457,34.035 C57.086,53.198 98.208,65.809 123.822,71.865 C181.454,85.495 234.295,90.29 272.033,93.459 C311.355,96.759 396.635,95.801 461.025,91.663 C486.76,90.01 518.727,86.372 556.926,80.752 C595.747,74.596 622.372,70.008 636.799,66.991 C663.913,61.324 712.501,49.503 727.605,46.128 C780.47,34.317 818.839,22.532 856.324,15.904 C922.689,4.169 955.676,2.522 1011.185,0.432 C1060.705,1.477 1097.39,3.129 1121.236,5.387 C1161.703,9.219 1208.621,17.821 1235.4,22.304 C1285.855,30.748 1354.351,47.432 1440.886,72.354 L1441.191,104.352 L1.121,104.031 L0.457,34.035 Z"
            />
          </g>
        </g>
      </svg>
    </div>

    <section class="bg-white py-8">
      <div class="container max-w-5xl mx-auto">
        <h2
          class="w-full my-2 text-3xl font-semibold leading-tight text-center text-gray-800"
        >Create your cluster in some minutes</h2>
        <p
          class="text-cool-gray-500 text-center pb-8 text-xl max-w-3xl mx-auto"
        >You chose one of our supported cloud providers, and we set up an Elasticsearch cluster on your behalf.</p>
        <div class="w-full mb-4 mt-2">
          <div class="h-1 mx-auto gradient w-64 opacity-25 my-0 py-0 rounded-t"></div>
        </div>

        <div class="flex flex-row flex-wrap-reverse sm:flex-wrap">
          <div class="w-5/6 sm:w-1/2 p-6">
            <h3
              class="text-2xl text-gray-800 font-semibold kleading-none mb-3"
            >Keep an eye on everything</h3>
            <p class="text-gray-600 mb-8">
              We monitor your cluster, providing you an overview with variety of
              information about your cluster's health.
              <br />
              <br />We even notify you when something doesn't seem right,
              so that you never lose sight of your cluster.
              <br />
              <br />You can also  query your search from inside our application, to get a detailed overview
              regarding your results.
            </p>
          </div>
          <div class="w-full sm:w-1/2 p-6">
            <illustration-analysis class="w-5/6 sm:h-64 mx-auto"></illustration-analysis>
          </div>
        </div>

        <div class="flex flex-wrap sm:flex-row">
          <div class="w-full sm:w-1/2 p-6 mt-6">
            <illustration-metrics class="w-full sm:h-64 mx-auto"></illustration-metrics>
          </div>
          <div class="w-full sm:w-1/2 p-6 mt-6">
            <div class="align-middle">
              <h3
                class="text-2xl text-cool-gray-800 font-semibold kleading-none mb-3"
              >Save yourself trouble and work</h3>
              <p class="text-cool-gray-600 mb-8">
                We hide your cluster's real IP address behind Cloudflare, and
                configure a loadbalancer with a basic authentication to keep you safe.
                <br />
                <br />You are able to query your search directly from your application's frontend using
                our proxy service, without the need for a backend controller.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section-pricing></section-pricing>

    <section style="background-color: #f8fafc" class="bg-white px-16 py-16">
      <div class="flex flex-col justify-center container mx-auto px-4">
        <h1 class="text-2xl text-center font-normal text-cool-gray-600 pb-1">Wan't to learn more ?</h1>
        <p
          class="text-lg text-center font-normal text-cool-gray-400 pb-4"
        >Check out our documentation for guides and more!</p>
        <a
          href="https://docs.sigmie.com/app"
          target="_blank"
          class="bg-theme-secondary-lighter w-60 md:w-48 flex items-center justify-center px-4 rounded-lg h-12 self-center text-sm leading-5 text-white"
        >Read the docs</a>
      </div>
    </section>

    <svg
      class="wave-top"
      viewBox="0 0 1439 147"
      version="1.1"
      xmlns="http://www.w3.org/2000/svg"
      xmlns:xlink="http://www.w3.org/1999/xlink"
    >
      <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
        <g transform="translate(-1.000000, -14.000000)" fill-rule="nonzero">
          <g class="wave" fill="#f8fafc">
            <path
              d="M1440,84 C1383.555,64.3 1342.555,51.3 1317,45 C1259.5,30.824 1206.707,25.526 1169,22 C1129.711,18.326 1044.426,18.475 980,22 C954.25,23.409 922.25,26.742 884,32 C845.122,37.787 818.455,42.121 804,45 C776.833,50.41 728.136,61.77 713,65 C660.023,76.309 621.544,87.729 584,94 C517.525,105.104 484.525,106.438 429,108 C379.49,106.484 342.823,104.484 319,102 C278.571,97.783 231.737,88.736 205,84 C154.629,75.076 86.296,57.743 0,32 L0,0 L1440,0 L1440,84 Z"
            />
          </g>
          <g transform="translate(1.000000, 15.000000)" fill="#FFFFFF">
            <g
              transform="translate(719.500000, 68.500000) rotate(-180.000000) translate(-719.500000, -68.500000) "
            >
              <path
                d="M0,0 C90.7283404,0.927527913 147.912752,27.187927 291.910178,59.9119003 C387.908462,81.7278826 543.605069,89.334785 759,82.7326078 C469.336065,156.254352 216.336065,153.6679 0,74.9732496"
                opacity="0.100000001"
              />
              <path
                d="M100,104.708498 C277.413333,72.2345949 426.147877,52.5246657 546.203633,45.5787101 C666.259389,38.6327546 810.524845,41.7979068 979,55.0741668 C931.069965,56.122511 810.303266,74.8455141 616.699903,111.243176 C423.096539,147.640838 250.863238,145.462612 100,104.708498 Z"
                opacity="0.100000001"
              />
              <path
                d="M1046,51.6521276 C1130.83045,29.328812 1279.08318,17.607883 1439,40.1656806 L1439,120 C1271.17211,77.9435312 1140.17211,55.1609071 1046,51.6521276 Z"
                opacity="0.200000003"
              />
            </g>
          </g>
        </g>
      </g>
    </svg>

    <section id="subscribe" class="container mx-auto text-center pt-6">
      <h3 class="my-4 text-3xl leading-tight">Subscribe for project updates</h3>

      <div class="w-full pt-4">
        <div class="h-1 mx-auto bg-white w-1/6 opacity-25 my-0 py-0 rounded-t"></div>
      </div>

      <form
        method="POST"
        :action="$route('newsletter.subscription.store')"
        @submit.prevent="submit"
        class="inline-block pt-4 w-full px-6 pb-4"
      >
        <div class="inline-block w-full sm:max-w-xs pr-4">
          <input
            class="bg-white text-cool-gray-600 rounded-lg focus:outline-none focus:shadow-outline border border-cool-gray-300 py-3 px-8 block w-full appearance-none leading-normal"
            name="email"
            v-model="email"
            required
            type="email"
            placeholder="jane@example.com"
          />
        </div>
        <div class="inline-block w-full sm:w-auto">
          <button
            type="submit"
            class="mx-auto lg:mx-0 text-theme-secondary tracking-wide bg-white hover:bg-cool-gray-50 font-semibold rounded-lg my-6 py-3 px-8 shadow cursor-pointer transition-shadow"
          >Subscribe</button>
        </div>
      </form>
    </section>
    <div class="px-7 space-x-8 mx-auto w-4/5 justify-center flex-wrap flex pb-3">
      <div class="py-5">
        <inertia-link :href="$route('legal.about')">About Us</inertia-link>
      </div>
      <div class="py-5">
        <inertia-link :href="$route('legal.terms')">Terms of Service</inertia-link>
      </div>
      <div class="py-5">
        <inertia-link :href="$route('legal.privacy')">Privacy Policy</inertia-link>
      </div>
      <div class="py-5">
        <inertia-link :href="$route('legal.imprint')">Imprint</inertia-link>
      </div>
      <div class="py-5">
        <inertia-link :href="$route('legal.disclaimer')">Disclaimer</inertia-link>
      </div>
    </div>
    <p class="text-center w-full opacity-75 px-5 sm:px-0 sm:w-2/3 md:w-8/12 pb-15 mx-auto py-3">
      Sigmie UG (haftungsbeschränkt), Feldstr. 9, 58507 Lüdenscheid, Germany
      <br />Commercial Register: Amtsgericht Iserlohn, HRB 9701
      <br />VAT ID: DE000000000 / Managing Partner: Markos Nikolaos Orfanos
      <br />
    </p>
  </div>
</template>

<script>
export default {
  components: {
    illustrationAnalysis: require("./illustrations/analysis").default,
    illustrationMetrics: require("./illustrations/metrics").default,
    illustrationHero: require("./illustrations/hero").default,
    sectionPricing: require("./sections/pricing").default,
  },
  data() {
    return {
      email: "",
    };
  },
  methods: {
    submit() {
      this.$inertia.post(this.$route("newsletter.subscription.store"), {
        email: this.email,
      });
    },
  },
};
</script>

<style scoped>
.gradient {
  background: linear-gradient(90deg, #d53369 0%, #daae51 100%);
}
</style>
