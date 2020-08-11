<template>
  <div
    class="leading-normal tracking-normal text-white gradient"
    style="font-family: 'Source Sans Pro', sans-serif;"
  >
    <vue-headful title="Sigmie Application | Awesome search on your Infastructure" />

    <nav id="header" class="fixed w-full bg-white shadow z-30 top-0 text-white py-1">
      <div class="w-full container mx-auto flex flex-wrap items-center justify-between mt-0 py-2">
        <div class="pl-4 flex items-center">
          <div
            class="toggleColour text-white no-underline hover:no-underline font-bold text-2xl lg:text-4xl"
          >
            <logo-default class="lg:pl-24 h-16"></logo-default>
          </div>
        </div>

        <div
          class="w-full flex-grow lg:flex lg:items-center lg:w-auto mt-2 lg:mt-0 bg-white lg:bg-transparent text-black p-4 lg:p-0 z-20"
        >
          <ul class="list-reset lg:flex justify-end flex-1 items-center">
            <li>
              <span class="sm:block rounded-md">
                <inertia-link
                  :href="$route('register')"
                  class="inline-flex items-center px-4 py-2 border border-transparent text-sm leading-5 font-semibold rounded-md text-gray-800 tracking-wide bg-white hover:bg-gray-50 focus:outline-none focus:border-gray-700 active:bg-gray-100 transition duration-150 ease-in-out"
                >Register</inertia-link>
              </span>
            </li>

            <li>
              <span class="hidden sm:block ml-3 rounded-md">
                <inertia-link
                  :href="$route('login')"
                  class="inline-flex items-center px-4 py-2 border border-transparent text-sm leading-5 font-semibold rounded-md text-gray-800 tracking-wide bg-white hover:bg-gray-50 focus:outline-none focus:border-gray-700 active:bg-gray-100 transition duration-150 ease-in-out"
                >Login</inertia-link>
              </span>
            </li>
          </ul>
        </div>
      </div>
    </nav>

    <div class="pt-32 pb-10">
      <div class="container px-3 mx-auto flex flex-wrap flex-col md:flex-row items-center lg:px-24">
        <div
          class="flex flex-col order-2 md:order-1 md:w-3/5 justify-center items-start text-center md:text-left md:pr-10 lg:pr-20 pb-4"
        >
          <h1
            class="mt-9 font-display text-4xl leading-none font-semibold text-white sm:text-5xl lg:text-4xl pb-2"
          >Awesome search, on your infastructure.</h1>
          <p class="leading-normal text-2xl mb-0 md:mb-3">
            Imagine having a smart, reliable, and scalable On-Site search, with the power of
            Elasticsearch, running on your Cloud Infastructure.
          </p>

          <a
            :href="$route('register')"
            class="mx-auto lg:mx-0 tracking-wide bg-white hover:bg-gray-50 text-gray-600 font-normal rounded-lg my-6 py-3 px-8 shadow cursor-pointer transition-shadow"
          >Get early access</a>
        </div>

        <div class="w-full order-1 md:order-2 py-6 md:w-2/5 text-center">
          <illustration-hero class="m-auto max-w-xs z-50"></illustration-hero>
        </div>
      </div>
    </div>

    <div class="relative -mt-12 lg:-mt-24">
      <svg
        viewBox="0 0 1428 174"
        version="1.1"
        xmlns="http://www.w3.org/2000/svg"
        xmlns:xlink="http://www.w3.org/1999/xlink"
      >
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

    <section class="bg-white border-b py-8">
      <div class="container max-w-5xl mx-auto m-8">
        <h1 class="w-full my-2 text-5xl font-bold leading-tight text-center text-gray-800">
          Set up your search engine
          on your Cloud platform
        </h1>
        <div class="w-full mb-4">
          <div class="h-1 mx-auto gradient w-64 opacity-25 my-0 py-0 rounded-t"></div>
        </div>

        <div class="flex flex-row flex-wrap-reverse sm:flex-wrap">
          <div class="w-5/6 sm:w-1/2 p-6">
            <h3 class="text-3xl text-gray-800 font-bold leading-none mb-3">Cluster metrics</h3>
            <p class="text-gray-600 mb-8">
              We monitor your cluster, providing you an overview with variety of
              information.
              <br />
              <br />We even notify you when something doesn't seem right, so that you never lose sight of your
              clusters health, size, documents count, memory, storage and more.
            </p>
          </div>
          <div class="w-full sm:w-1/2 p-6">
            <illustration-metrics class="w-full sm:h-64 mx-auto"></illustration-metrics>
          </div>
        </div>

        <div class="flex flex-wrap sm:flex-row">
          <div class="w-full sm:w-1/2 p-6 mt-6">
            <illustration-analysis class="w-5/6 sm:h-64 mx-auto"></illustration-analysis>
          </div>
          <div class="w-full sm:w-1/2 p-6 mt-6">
            <div class="align-middle">
              <h3 class="text-3xl text-gray-800 font-bold leading-none mb-3">Instant analysis</h3>
              <p class="text-gray-600 mb-8">
                Each Sigmie project contains it's own Playground, to help you
                aim for the perfect search results.
                <br />
                <br />Through the Playground interface your can send queries to your search and instantly recieve
                various search metrics about your search results. In addition to the standard search
                functionality you can play around with score, mappings, analyzers and tokenziers.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="bg-white border-b py-8">
      <div class="container mx-auto flex flex-wrap pt-4 pb-12">
        <h1
          class="w-full my-2 text-5xl font-bold leading-tight text-center text-gray-800"
        >Simple steps</h1>
        <div class="w-full mb-4">
          <div class="h-1 mx-auto gradient w-64 opacity-25 my-0 py-0 rounded-t"></div>
        </div>

        <div class="w-full md:w-1/3 p-6 flex flex-col flex-grow flex-shrink">
          <div class="flex-none mt-auto bg-white rounded-b rounded-t-none overflow-hidden p-6">
            <div class="flex items-center justify-start">
              <svg
                width="80px"
                height="53px"
                viewBox="0 0 60 40"
                version="1.1"
                xmlns="http://www.w3.org/2000/svg"
                xmlns:xlink="http://www.w3.org/1999/xlink"
              >
                <g
                  id="Page-1"
                  stroke="none"
                  stroke-width="1"
                  fill="none"
                  fill-rule="evenodd"
                  stroke-linecap="round"
                >
                  <g
                    id="cloud"
                    transform="translate(1.000000, 1.000000)"
                    fill="#FF6347"
                    fill-rule="nonzero"
                    stroke="#3F3D55"
                    stroke-width="2"
                  >
                    <path
                      d="M49.003,16 C48.888,7.301 41.81,0 33.084,0 C27.525,0 22.305,3.005 19.423,7.336 C18.157,6.493 16.636,6 15,6 C10.582,6 7,9.582 7,14 C7,14.153 7.014,14.302 7.023,14.454 C7.013,14.636 7,14.82 7,15 C3.012,16.912 0,21.457 0,26.155 C0,32.67 5.33,38 11.845,38 L36.352,38 C36.49,38 36.624,37.984 36.76,37.979 C36.897,37.984 37.031,38 37.169,38 L46.972,38 C53.037,38 58,33.037 58,26.972 C58,21.601 54.106,16.961 49.003,16 Z"
                      id="Path"
                    />
                    <path d="M49.003,16 C49.003,16 46.468,15.625 44,16" id="Path" />
                    <path
                      d="M7,14 C7,11.8654903 7.86156608,9.91350695 9.2241297,8.4789649 C10.6817951,6.94429724 12.7165097,6 15,6 C19.418,6 23,9.582 23,14"
                      id="Path"
                    />
                  </g>
                </g>
              </svg>
            </div>
          </div>
          <div class="flex-1 bg-white rounded-t rounded-b-none overflow-hidden">
            <a href="#" class="flex flex-wrap no-underline hover:no-underline">
              <div class="w-full font-bold text-xl text-gray-800 px-6 pb-1">Link your Cloud</div>
              <p
                class="text-gray-800 text-base px-6 mb-5"
              >Link your Cloud account and let us do the hard lifting for you, keeping the full control.</p>
            </a>
          </div>
        </div>

        <div class="w-full md:w-1/3 p-6 flex flex-col flex-grow flex-shrink">
          <div class="flex-none mt-auto bg-white rounded-b rounded-t-none overflow-hidden p-6">
            <div class="flex items-center justify-start">
              <svg
                width="56px"
                height="56px"
                viewBox="0 0 54 54"
                version="1.1"
                xmlns="http://www.w3.org/2000/svg"
                xmlns:xlink="http://www.w3.org/1999/xlink"
              >
                <g id="Page-1" stroke="none" stroke-width="2" fill="none" fill-rule="evenodd">
                  <g
                    id="settings-(1)"
                    transform="translate(1.000000, -69.000000)"
                    fill-rule="nonzero"
                  >
                    <g id="settings-(2)" transform="translate(0.000000, 70.000000)">
                      <path
                        d="M48.872,22.898 C44.229,22.005 42.1,16.58 44.896,12.768 L46.86,10.09 L42.617,5.847 L39.979,7.634 C36.065,10.286 30.723,7.955 30.005,3.283 L29.5,0 L23.5,0 L22.723,4.041 C21.85,8.581 16.618,10.749 12.79,8.156 L9.383,5.847 L5.14,10.09 L7.104,12.768 C9.9,16.58 7.77,22.005 3.128,22.898 L0,23.5 L0,29.5 L3.283,30.005 C7.956,30.724 10.286,36.066 7.634,39.98 L5.847,42.617 L10.09,46.86 L12.768,44.896 C16.58,42.1 22.005,44.229 22.898,48.872 L23.5,52 L29.5,52 L29.855,49.691 C30.59,44.915 36.129,42.62 40.026,45.478 L41.91,46.86 L46.153,42.617 L44.366,39.98 C41.715,36.066 44.045,30.724 48.717,30.005 L52,29.5 L52,23.5 L48.872,22.898 Z M26.5,31 C24.015,31 22,28.985 22,26.5 C22,24.015 24.015,22 26.5,22 C28.985,22 31,24.015 31,26.5 C31,28.985 28.985,31 26.5,31 Z"
                        id="Shape"
                        stroke="#3F3D55"
                        fill="#FF6347"
                      />
                      <path
                        d="M26.5,17 C21.253,17 17,21.253 17,26.5 C17,31.747 21.253,36 26.5,36 C31.747,36 36,31.747 36,26.5 C36,21.253 31.747,17 26.5,17 Z M26.5,31 C24.015,31 22,28.985 22,26.5 C22,24.015 24.015,22 26.5,22 C28.985,22 31,24.015 31,26.5 C31,28.985 28.985,31 26.5,31 Z"
                        id="Shape"
                        fill="#3F3D55"
                      />
                    </g>
                  </g>
                </g>
              </svg>
            </div>
          </div>
          <div class="flex-1 bg-white rounded-t rounded-b-none overflow-hidden">
            <a href="#" class="flex flex-wrap no-underline hover:no-underline">
              <div class="w-full font-bold text-xl text-gray-800 px-6 pb-1">Set your preferences</div>
              <p class="text-gray-800 text-base px-6 mb-5">
                Choose your desirable data center location and amount of nodes, let the rest to be done for
                you.
              </p>
            </a>
          </div>
        </div>

        <div class="w-full md:w-1/3 p-6 flex flex-col flex-grow flex-shrink">
          <div class="flex-none mt-auto bg-white rounded-b rounded-t-none overflow-hidden p-6">
            <div class="flex items-center justify-start">
              <svg
                width="56px"
                height="57px"
                viewBox="0 0 56 57"
                version="1.1"
                xmlns="http://www.w3.org/2000/svg"
                xmlns:xlink="http://www.w3.org/1999/xlink"
              >
                <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                  <g
                    id="search"
                    transform="translate(1.000000, 1.000000)"
                    fill="#FF6347"
                    fill-rule="nonzero"
                    stroke="#3F3D55"
                    stroke-width="2"
                  >
                    <path
                      d="M53.1765,50.0338929 L40.1027143,36.4365 C43.4642143,32.4405 45.306,27.4127143 45.306,22.1785714 C45.306,9.9495 35.3565,0 23.1274286,0 C10.8983571,0 0.948857143,9.9495 0.948857143,22.1785714 C0.948857143,34.4076429 10.8983571,44.3571429 23.1274286,44.3571429 C27.7183929,44.3571429 32.0933571,42.9724286 35.8338214,40.3437857 L49.0069286,54.0443571 C49.5575357,54.6161786 50.2981071,54.9315 51.0917143,54.9315 C51.8428929,54.9315 52.5555,54.6451071 53.0964643,54.1243929 C54.2458929,53.0183571 54.2825357,51.1842857 53.1765,50.0338929 Z M23.1274286,5.78571429 C32.1666429,5.78571429 39.5202857,13.1393571 39.5202857,22.1785714 C39.5202857,31.2177857 32.1666429,38.5714286 23.1274286,38.5714286 C14.0882143,38.5714286 6.73457143,31.2177857 6.73457143,22.1785714 C6.73457143,13.1393571 14.0882143,5.78571429 23.1274286,5.78571429 Z"
                      id="Shape"
                    />
                  </g>
                </g>
              </svg>
            </div>
          </div>
          <div class="flex-1 bg-white rounded-t rounded-b-none overflow-hidden">
            <a href="#" class="flex flex-wrap no-underline hover:no-underline">
              <div class="w-full font-bold text-xl text-gray-800 px-6 pb-1">Enjoy your search</div>
              <p
                class="text-gray-800 text-base px-6 mb-5"
              >Enjoy the intuitive dashboard, and have always the complete overview over your search.</p>
            </a>
          </div>
        </div>
      </div>
    </section>

    <section-pricing></section-pricing>

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
      <h3 class="my-4 text-3xl leading-tight">Subscribe for project updates and early releases.</h3>

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
            class="bg-white text-gray-600 rounded-lg focus:outline-none focus:shadow-outline border border-gray-300 py-3 px-8 block w-full appearance-none leading-normal"
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
            class="mx-auto lg:mx-0 tracking-wide bg-white hover:bg-gray-50 text-gray-800 font-bold rounded-lg my-6 py-3 px-8 shadow cursor-pointer transition-shadow"
          >Subscribe</button>
        </div>
      </form>
    </section>
    <div class="px-7 mx-auto w-full text-center inline-block pb-3">
      <inertia-link :href="$route('legal.about')" class="pr-10">About Us</inertia-link>
      <inertia-link class="pr-10" :href="$route('legal.terms')">Terms of Service</inertia-link>
      <inertia-link class="pr-10" :href="$route('legal.privacy')">Privacy Policy</inertia-link>
      <inertia-link class="pr-10" :href="$route('legal.imprint')">Imprint</inertia-link>
      <inertia-link :href="$route('legal.disclaimer')">Disclaimer</inertia-link>
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
